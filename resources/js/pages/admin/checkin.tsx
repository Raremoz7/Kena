import { Head } from '@inertiajs/react';
import type { IScannerControls } from '@zxing/browser';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/atoms/Button';
import { Icon } from '@/components/atoms/Icon';
import { Input } from '@/components/atoms/Input';
import { CheckinResultDialog } from '@/components/organisms/CheckinResultDialog';
import type { CheckinOutcome } from '@/components/organisms/CheckinResultDialog';
import { api, ApiError } from '@/lib/veludo/api';
import { veludoToast } from '@/lib/veludo/toast';

interface SessionProgress {
    checkedIn: number;
    total: number;
}

interface SessionOption {
    id: number;
    eventTitle: string;
    label: string;
    progress: SessionProgress;
}

interface ScanTicket {
    id: number;
    code: string;
    holderName: string;
    sectorName: string;
    seatLabel: string;
    checkedInAt: string | null;
}

interface ScanResult {
    result: 'ok' | 'denied' | 'pending';
    reason: string | null;
    ticket: ScanTicket | null;
    progress: SessionProgress;
}

// BarcodeDetector é nativo no Chromium — usado quando disponível.
interface DetectedBarcode {
    rawValue: string;
}
interface BarcodeDetectorLike {
    detect(source: CanvasImageSource): Promise<DetectedBarcode[]>;
}
declare global {
    interface Window {
        BarcodeDetector?: new (opts?: {
            formats?: string[];
        }) => BarcodeDetectorLike;
    }
}

interface LookupResult {
    id: number;
    code: string;
    holder: string;
    seat: string;
    used: boolean;
}

interface CheckinPageProps {
    sessions: SessionOption[];
    previewUrl: string;
    lookupUrl: string;
    admitUrl: string;
}

function toOutcome(res: ScanResult): CheckinOutcome {
    if (res.result === 'pending' && res.ticket) {
        return { kind: 'pending', ticket: res.ticket };
    }

    if (res.result === 'ok') {
        return { kind: 'ok', ticket: res.ticket };
    }

    return { kind: 'denied', reason: res.reason, ticket: res.ticket };
}

/** Vibração curta no celular da portaria: um toque libera, três negam. */
function buzz(success: boolean) {
    navigator.vibrate?.(success ? 60 : [80, 60, 80, 60, 80]);
}

export default function CheckinPage({
    sessions,
    previewUrl,
    lookupUrl,
    admitUrl,
}: CheckinPageProps) {
    const [sessionId, setSessionId] = useState<number>(sessions[0]?.id ?? 0);
    const [token, setToken] = useState('');
    const [busy, setBusy] = useState(false);
    const [outcome, setOutcome] = useState<CheckinOutcome | null>(null);
    const [confirming, setConfirming] = useState(false);
    // A câmera lê em loop: trava enquanto o popup está aberto e ignora o mesmo
    // QR por alguns segundos depois de fechar (senão ele reabre na hora).
    const lockRef = useRef(false);
    const lastScanRef = useRef<{ code: string; at: number } | null>(null);
    const scannedRef = useRef<string | null>(null);
    const [progress, setProgress] = useState<SessionProgress>(
        sessions[0]?.progress ?? { checkedIn: 0, total: 0 },
    );
    const [cameraOn, setCameraOn] = useState(false);
    const videoRef = useRef<HTMLVideoElement | null>(null);

    const [lookupQuery, setLookupQuery] = useState('');
    const [lookupResults, setLookupResults] = useState<LookupResult[]>([]);
    const [lookingUp, setLookingUp] = useState(false);

    async function doLookup() {
        const q = lookupQuery.trim();

        if (q.length < 2 || lookingUp || !sessionId) {
            return;
        }

        setLookingUp(true);

        try {
            const res = await api.post<{ results: LookupResult[] }>(lookupUrl, {
                session_id: sessionId,
                q,
            });
            setLookupResults(res.results);
        } catch (e) {
            const message =
                e instanceof ApiError ? e.message : 'Falha na busca.';
            veludoToast.error('Erro', message);
        } finally {
            setLookingUp(false);
        }
    }

    function showOutcome(next: CheckinOutcome) {
        lockRef.current = true;
        setOutcome(next);

        if (next.kind !== 'pending') {
            buzz(next.kind === 'ok');
        }
    }

    function closeOutcome() {
        if (scannedRef.current) {
            lastScanRef.current = { code: scannedRef.current, at: Date.now() };
        }

        scannedRef.current = null;

        setOutcome(null);
        lockRef.current = false;
    }

    // Entrada liberada fecha sozinha para a fila andar; negado espera o toque.
    useEffect(() => {
        if (outcome?.kind !== 'ok') {
            return;
        }

        const timer = window.setTimeout(closeOutcome, 2200);

        return () => window.clearTimeout(timer);
    }, [outcome]);

    /** Busca manual: abre o mesmo popup de confirmação da leitura por QR. */
    function reviewLookup(r: LookupResult) {
        const [sectorName, seatLabel] = r.seat.split(' · ');
        showOutcome({
            kind: 'pending',
            ticket: {
                id: r.id,
                code: r.code,
                holderName: r.holder,
                sectorName: sectorName ?? r.seat,
                seatLabel: seatLabel ?? '',
            },
        });
    }

    async function confirmEntry() {
        if (outcome?.kind !== 'pending' || confirming) {
            return;
        }

        setConfirming(true);

        try {
            const res = await api.post<ScanResult>(admitUrl, {
                session_id: sessionId,
                ticket_id: outcome.ticket.id,
            });
            setProgress(res.progress);
            setLookupResults([]);
            setLookupQuery('');
            showOutcome(toOutcome(res));
        } catch (e) {
            const message =
                e instanceof ApiError ? e.message : 'Falha ao admitir.';
            veludoToast.error('Erro', message);
        } finally {
            setConfirming(false);
        }
    }

    function selectSession(id: number) {
        setSessionId(id);
        setOutcome(null);
        lockRef.current = false;
        setProgress(
            sessions.find((s) => s.id === id)?.progress ?? {
                checkedIn: 0,
                total: 0,
            },
        );
    }

    async function doScan(value: string) {
        const code = value.trim();

        if (!code || busy || lockRef.current || !sessionId) {
            return;
        }

        const last = lastScanRef.current;

        if (last && last.code === code && Date.now() - last.at < 3000) {
            return;
        }

        lockRef.current = true;
        scannedRef.current = code;
        setBusy(true);

        try {
            const res = await api.post<ScanResult>(previewUrl, {
                token: code,
                session_id: sessionId,
            });
            setProgress(res.progress);
            setToken('');
            showOutcome(toOutcome(res));
        } catch (e) {
            lockRef.current = false;
            const message =
                e instanceof ApiError
                    ? e.message
                    : 'Falha ao validar. Tente novamente.';
            veludoToast.error('Erro na leitura', message);
        } finally {
            setBusy(false);
        }
    }

    // Câmera: BarcodeDetector nativo (Chromium) com fallback ZXing (Safari/iOS, Firefox).
    useEffect(() => {
        if (!cameraOn) {
            return;
        }

        if (!navigator.mediaDevices) {
            veludoToast.warning(
                'Câmera indisponível',
                'Use a leitura manual do código.',
            );
            setCameraOn(false);

            return;
        }

        let stopped = false;
        let stream: MediaStream | null = null;
        let timer: number | null = null;
        let zxing: IScannerControls | null = null;

        async function startNative(): Promise<boolean> {
            if (!window.BarcodeDetector) {
                return false;
            }

            stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'environment' },
            });
            const video = videoRef.current;

            if (!video || stopped) {
                return true;
            }

            video.srcObject = stream;
            await video.play();
            const detector = new window.BarcodeDetector({
                formats: ['qr_code'],
            });
            timer = window.setInterval(async () => {
                if (!videoRef.current || lockRef.current) {
                    return;
                }

                try {
                    const codes = await detector.detect(videoRef.current);

                    if (codes.length > 0) {
                        void doScan(codes[0].rawValue);
                    }
                } catch {
                    /* frame sem leitura */
                }
            }, 700);

            return true;
        }

        async function startZxing() {
            const { BrowserQRCodeReader } = await import('@zxing/browser');
            const reader = new BrowserQRCodeReader();
            zxing = await reader.decodeFromConstraints(
                { video: { facingMode: 'environment' } },
                videoRef.current!,
                (res) => {
                    if (res && !stopped) {
                        void doScan(res.getText());
                    }
                },
            );

            if (stopped) {
                zxing.stop();
                zxing = null;
            }
        }

        async function start() {
            try {
                if (!(await startNative())) {
                    await startZxing();
                }
            } catch {
                veludoToast.warning(
                    'Câmera bloqueada',
                    'Autorize o acesso ou use a leitura manual.',
                );
                setCameraOn(false);
            }
        }
        void start();

        return () => {
            stopped = true;

            if (timer !== null) {
                window.clearInterval(timer);
            }

            zxing?.stop();
            stream?.getTracks().forEach((t) => t.stop());
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [cameraOn, sessionId]);

    const pct =
        progress.total > 0
            ? Math.round((progress.checkedIn / progress.total) * 100)
            : 0;

    return (
        <>
            <Head title="Check-in" />
            <div className="mx-auto flex max-w-xl flex-col gap-5 px-4 py-6 sm:px-6 sm:py-8">
                <div>
                    <h1 className="font-display text-display-sm text-foreground uppercase">
                        Check-in
                    </h1>
                    <p className="mt-1 font-body text-sm text-muted-foreground">
                        Leia o QR do ingresso para liberar a entrada.
                    </p>
                </div>

                <div className="flex flex-col gap-2">
                    <label htmlFor="ck-session" className="kicker text-faint">
                        Sessão
                    </label>
                    <select
                        id="ck-session"
                        className="w-full rounded-input border border-border-strong bg-bg px-[14px] py-3 font-body text-sm text-foreground outline-none focus-visible:border-accent"
                        value={sessionId}
                        onChange={(e) => selectSession(Number(e.target.value))}
                    >
                        {sessions.map((s) => (
                            <option key={s.id} value={s.id}>
                                {s.eventTitle} · {s.label}
                            </option>
                        ))}
                    </select>
                </div>

                {/* Câmera / leitura */}
                <div className="overflow-hidden rounded-card border border-border bg-bg">
                    <div className="relative aspect-square w-full bg-black/60">
                        {cameraOn ? (
                            <video
                                ref={videoRef}
                                muted
                                playsInline
                                className="size-full object-cover"
                            />
                        ) : (
                            <div className="flex size-full flex-col items-center justify-center gap-3 text-faint">
                                <Icon name="qr" size={48} />
                                <p className="font-body text-xs">
                                    Câmera desligada
                                </p>
                            </div>
                        )}
                        <div className="pointer-events-none absolute inset-8 rounded-[12px] border-2 border-white/40" />
                    </div>
                    <div className="flex gap-2 border-t border-border p-3">
                        <Button
                            variant={cameraOn ? 'secondary' : 'primary'}
                            size="sm"
                            onClick={() => setCameraOn((v) => !v)}
                        >
                            <Icon name="qr" size={15} />
                            {cameraOn ? 'Desligar câmera' : 'Ativar câmera'}
                        </Button>
                    </div>
                </div>

                {/* Leitura manual */}
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        void doScan(token);
                    }}
                    className="flex gap-2"
                >
                    <Input
                        value={token}
                        onChange={(e) => setToken(e.target.value)}
                        placeholder="Cole o código do QR"
                        aria-label="Código do ingresso"
                        className="font-mono"
                    />
                    <Button type="submit" className="shrink-0" disabled={busy}>
                        Validar
                    </Button>
                </form>

                {/* Busca manual (esqueceu o celular) */}
                <div className="rounded-card border border-border bg-surface p-4">
                    <p className="kicker text-faint">Busca manual</p>
                    <p className="mt-1 font-body text-xs text-muted-foreground">
                        Sem o QR? Busque por nome, código ou assento e admita na
                        mão.
                    </p>
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            void doLookup();
                        }}
                        className="mt-3 flex gap-2"
                    >
                        <Input
                            value={lookupQuery}
                            onChange={(e) => setLookupQuery(e.target.value)}
                            placeholder="Nome, código ou assento"
                            aria-label="Buscar ingresso"
                        />
                        <Button
                            type="submit"
                            variant="secondary"
                            className="shrink-0"
                            disabled={lookingUp}
                        >
                            Buscar
                        </Button>
                    </form>

                    {lookupResults.length > 0 && (
                        <ul className="mt-3 flex flex-col gap-2">
                            {lookupResults.map((r) => (
                                <li
                                    key={r.id}
                                    className="flex items-center justify-between gap-3 rounded-btn border border-border bg-bg px-3 py-2"
                                >
                                    <div className="min-w-0">
                                        <p className="truncate font-body text-sm font-medium text-foreground">
                                            {r.holder}
                                        </p>
                                        <p className="truncate font-body text-xs text-faint">
                                            {r.seat} · {r.code}
                                        </p>
                                    </div>
                                    {r.used ? (
                                        <span className="shrink-0 font-body text-xs text-faint">
                                            Já usado
                                        </span>
                                    ) : (
                                        <Button
                                            size="sm"
                                            onClick={() => reviewLookup(r)}
                                        >
                                            Admitir
                                        </Button>
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}
                </div>

                {/* Progresso */}
                <div className="rounded-card border border-border bg-surface p-4">
                    <div className="flex items-center justify-between font-body text-sm">
                        <span className="text-muted-foreground">Entradas</span>
                        <span className="font-display text-foreground">
                            {progress.checkedIn} / {progress.total}
                        </span>
                    </div>
                    <div className="mt-2 h-1 w-full overflow-hidden rounded-full bg-border">
                        <div
                            className="h-full rounded-full bg-success transition-all"
                            style={{ width: `${pct}%` }}
                        />
                    </div>
                </div>
            </div>

            <CheckinResultDialog
                outcome={outcome}
                confirming={confirming}
                onConfirm={() => void confirmEntry()}
                onClose={closeOutcome}
            />
        </>
    );
}
