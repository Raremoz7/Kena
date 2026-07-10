import { Head } from '@inertiajs/react';
import type { IScannerControls } from '@zxing/browser';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/atoms/Button';
import { Icon } from '@/components/atoms/Icon';
import { Input } from '@/components/atoms/Input';
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
    code: string;
    holderName: string;
    sectorName: string;
    seatLabel: string;
    checkedInAt: string | null;
}

interface ScanResult {
    result: 'ok' | 'denied';
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
    scanUrl: string;
    lookupUrl: string;
    admitUrl: string;
}

export default function CheckinPage({ sessions, scanUrl, lookupUrl, admitUrl }: CheckinPageProps) {
    const [sessionId, setSessionId] = useState<number>(sessions[0]?.id ?? 0);
    const [token, setToken] = useState('');
    const [busy, setBusy] = useState(false);
    const [result, setResult] = useState<ScanResult | null>(null);
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
            const message = e instanceof ApiError ? e.message : 'Falha na busca.';
            veludoToast.error('Erro', message);
        } finally {
            setLookingUp(false);
        }
    }

    async function admit(ticketId: number) {
        if (busy) {
            return;
        }

        setBusy(true);

        try {
            const res = await api.post<ScanResult>(admitUrl, {
                session_id: sessionId,
                ticket_id: ticketId,
            });
            setResult(res);
            setProgress(res.progress);
            setLookupResults([]);
            setLookupQuery('');
        } catch (e) {
            const message = e instanceof ApiError ? e.message : 'Falha ao admitir.';
            veludoToast.error('Erro', message);
        } finally {
            setBusy(false);
        }
    }

    function selectSession(id: number) {
        setSessionId(id);
        setResult(null);
        setProgress(
            sessions.find((s) => s.id === id)?.progress ?? {
                checkedIn: 0,
                total: 0,
            },
        );
    }

    async function doScan(value: string) {
        const code = value.trim();

        if (!code || busy || !sessionId) {
            return;
        }

        setBusy(true);

        try {
            const res = await api.post<ScanResult>(scanUrl, {
                token: code,
                session_id: sessionId,
            });
            setResult(res);
            setProgress(res.progress);
            setToken('');
        } catch (e) {
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
                if (!videoRef.current || busy) {
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
    const tint =
        result?.result === 'ok'
            ? 'border-success/50 bg-[oklch(0.16_0.04_155)]'
            : result?.result === 'denied'
              ? 'border-danger/50 bg-[oklch(0.17_0.025_27)]'
              : 'border-border bg-surface';

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
                        Sem o QR? Busque por nome, código ou assento e admita na mão.
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
                        <Button type="submit" variant="secondary" className="shrink-0" disabled={lookingUp}>
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
                                        <span className="shrink-0 font-body text-xs text-faint">Já usado</span>
                                    ) : (
                                        <Button size="sm" onClick={() => void admit(r.id)} disabled={busy}>
                                            Admitir
                                        </Button>
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}
                </div>

                {/* Resultado */}
                {result && (
                    <div className={`rounded-card border p-5 ${tint}`}>
                        <div className="flex items-center gap-3">
                            <span
                                className={`flex size-12 items-center justify-center rounded-full ${
                                    result.result === 'ok'
                                        ? 'bg-success text-white'
                                        : 'bg-danger text-white'
                                }`}
                            >
                                <Icon
                                    name={
                                        result.result === 'ok'
                                            ? 'check'
                                            : 'close'
                                    }
                                    size={26}
                                />
                            </span>
                            <div>
                                <p className="font-display text-display-sm text-foreground uppercase">
                                    {result.result === 'ok'
                                        ? 'Entrada liberada'
                                        : 'Acesso negado'}
                                </p>
                                {result.reason && (
                                    <p className="font-body text-sm text-muted-foreground">
                                        {result.reason}
                                    </p>
                                )}
                            </div>
                        </div>

                        {result.ticket && (
                            <dl className="mt-4 grid grid-cols-2 gap-3 border-t border-border pt-4 font-body text-sm">
                                <div>
                                    <dt className="kicker text-faint">
                                        Titular
                                    </dt>
                                    <dd className="text-foreground">
                                        {result.ticket.holderName}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="kicker text-faint">
                                        Assento
                                    </dt>
                                    <dd className="text-foreground">
                                        {result.ticket.sectorName} ·{' '}
                                        {result.ticket.seatLabel}
                                    </dd>
                                </div>
                                <div className="col-span-2">
                                    <dt className="kicker text-faint">
                                        Código
                                    </dt>
                                    <dd className="font-mono text-xs text-muted-foreground">
                                        {result.ticket.code}
                                    </dd>
                                </div>
                            </dl>
                        )}

                        <Button
                            variant="secondary"
                            block
                            className="mt-4"
                            onClick={() => setResult(null)}
                        >
                            Próxima leitura
                        </Button>
                    </div>
                )}

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
        </>
    );
}
