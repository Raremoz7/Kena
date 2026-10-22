import { Link } from '@inertiajs/react';
import {
    LegalSection,
    LegalShell,
    SupportContact,
} from '@/components/organisms/LegalPage';
import type { LegalPageProps } from '@/components/organisms/LegalPage';

export default function Help({ support, policy }: LegalPageProps) {
    return (
        <LegalShell
            title="Ajuda"
            intro={
                <p>
                    Dúvidas sobre compra, ingressos ou acesso à conta? Fale com
                    a gente: <SupportContact support={support} />.
                </p>
            }
        >
            <LegalSection title="Como compro?">
                <p>
                    Escolha o evento, a sessão e os assentos no mapa. Você pode
                    comprar com sua conta (e-mail e senha ou Google) ou só
                    informando nome, e-mail e CPF. Os assentos ficam reservados
                    para você por {policy.holdMinutes} minutos enquanto finaliza
                    o pagamento.
                </p>
                {policy.maxTickets !== null && (
                    <p>
                        Cada comprador pode levar até {policy.maxTickets}{' '}
                        ingressos por sessão.
                    </p>
                )}
            </LegalSection>

            <LegalSection title="Quais formas de pagamento?">
                <p>
                    Pix e cartão de crédito, processados pelo Mercado Pago. O
                    código Pix vale {policy.pixMinutes} minutos e os assentos
                    continuam reservados nesse período. Os dados do cartão não
                    passam pelo nosso servidor.
                </p>
            </LegalSection>

            <LegalSection title="Onde estão meus ingressos?">
                <p>
                    Assim que o pagamento é aprovado, enviamos os ingressos com
                    QR code para o seu e-mail. Eles também ficam em{' '}
                    <Link
                        href="/meus-ingressos"
                        className="text-foreground underline underline-offset-2"
                    >
                        Meus ingressos
                    </Link>
                    . Não achou o e-mail? Confira a caixa de spam e promoções.
                </p>
            </LegalSection>

            <LegalSection title="Comprei sem senha. Como entro na minha conta?">
                <p>
                    Use o botão “Ver meus ingressos” do e-mail de confirmação,
                    ou vá em{' '}
                    <Link
                        href="/forgot-password"
                        className="text-foreground underline underline-offset-2"
                    >
                        Esqueci minha senha
                    </Link>{' '}
                    para criar uma senha com o mesmo e-mail da compra.
                </p>
            </LegalSection>

            <LegalSection title="Posso passar o ingresso para outra pessoa?">
                <p>
                    Sim. Em Meus ingressos, use “Transferir” e informe o e-mail
                    de quem vai usar. O QR code antigo deixa de valer e a pessoa
                    recebe um novo por e-mail. A transferência fica disponível
                    até 1 dia antes da sessão.
                </p>
            </LegalSection>

            <LegalSection title="Como peço reembolso?">
                <p>
                    Em Meus ingressos, use “Pedir reembolso” até{' '}
                    {policy.refundDeadlineHours} horas antes da sessão. O valor
                    volta pelo mesmo meio de pagamento, no prazo do Mercado
                    Pago. Depois desse prazo, ou se algum ingresso do pedido já
                    foi transferido ou usado, fale com a gente.
                </p>
                <p>
                    Se o evento for cancelado, todos os pedidos são reembolsados
                    integralmente.
                </p>
            </LegalSection>

            <LegalSection title="O que levo no dia?">
                <p>
                    O QR code no celular (ou impresso) e um documento com foto,
                    se pedido na entrada. Cada QR vale uma entrada.
                </p>
            </LegalSection>
        </LegalShell>
    );
}
