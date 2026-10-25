import { Link } from '@inertiajs/react';
import {
    LegalSection,
    LegalShell,
    SupportContact,
} from '@/components/organisms/LegalPage';
import type { LegalPageProps } from '@/components/organisms/LegalPage';

export default function Terms({
    company,
    support,
    policy,
    updatedAt,
}: LegalPageProps) {
    return (
        <LegalShell
            title="Termos de uso"
            updatedAt={updatedAt}
            intro={
                <p>
                    Estes termos valem para quem compra ingressos no Kena,
                    plataforma operada por {company.name}
                    {company.document ? ` (${company.document})` : ''}. Ao
                    reservar assentos ou concluir uma compra, você concorda com
                    eles e com a{' '}
                    <Link
                        href="/privacidade"
                        className="text-foreground underline underline-offset-2"
                    >
                        Política de privacidade
                    </Link>
                    .
                </p>
            }
        >
            <LegalSection title="1. O papel do Kena">
                <p>
                    O Kena vende ingressos em nome do organizador de cada
                    evento. O conteúdo, a data, o local e a realização do evento
                    são responsabilidade do organizador, indicado na página do
                    evento.
                </p>
            </LegalSection>

            <LegalSection title="2. Conta e dados do comprador">
                <p>
                    Você pode comprar com uma conta (e-mail e senha ou Google)
                    ou informando nome, e-mail e CPF. Os dados precisam ser
                    verdadeiros e o e-mail precisa ser seu: é por ele que
                    enviamos os ingressos e o acesso à conta.
                </p>
                {policy.maxTickets !== null && (
                    <p>
                        Cada CPF pode adquirir até {policy.maxTickets}{' '}
                        ingressos por sessão, cortesias incluídas.
                    </p>
                )}
            </LegalSection>

            <LegalSection title="3. Reserva e pagamento">
                <ul>
                    <li>
                        Os assentos escolhidos ficam reservados por{' '}
                        {policy.holdMinutes} minutos para concluir o pagamento.
                    </li>
                    <li>
                        No Pix, a reserva acompanha a validade do código (
                        {policy.pixMinutes} minutos). Se o pagamento não for
                        confirmado nesse prazo, a reserva é cancelada e os
                        assentos voltam à venda.
                    </li>
                    <li>
                        Os pagamentos são processados pelo Mercado Pago. O Kena
                        não armazena dados de cartão.
                    </li>
                    <li>
                        O preço exibido no checkout, antes da confirmação, é o
                        valor total cobrado.
                    </li>
                </ul>
            </LegalSection>

            <LegalSection title="4. Ingressos">
                <ul>
                    <li>
                        Cada ingresso tem um QR code único, que vale uma única
                        entrada.
                    </li>
                    <li>
                        Não compartilhe o QR code: quem apresentar primeiro
                        entra. Ingressos comprados fora do Kena não têm
                        garantia.
                    </li>
                    <li>
                        A transferência para outra pessoa pode ser feita em Meus
                        ingressos até 1 dia antes da sessão. O QR code anterior
                        é invalidado.
                    </li>
                </ul>
            </LegalSection>

            <LegalSection title="5. Cancelamento e reembolso">
                <ul>
                    <li>
                        Você pode pedir o reembolso em Meus ingressos até{' '}
                        {policy.refundDeadlineHours} horas antes da sessão. O
                        estorno volta pelo mesmo meio de pagamento, no prazo do
                        Mercado Pago.
                    </li>
                    <li>
                        Pedidos com ingresso já transferido ou usado não são
                        reembolsados pela plataforma.
                    </li>
                    <li>
                        Se o evento for cancelado pelo organizador, todos os
                        pedidos são reembolsados integralmente. Em caso de
                        adiamento, os ingressos valem para a nova data, e quem
                        não puder comparecer pode pedir reembolso.
                    </li>
                    <li>
                        Isso não afasta os direitos previstos no Código de
                        Defesa do Consumidor. Para casos fora destas regras,
                        fale com a gente.
                    </li>
                </ul>
            </LegalSection>

            <LegalSection title="6. Uso indevido">
                <p>
                    Podemos cancelar reservas ou pedidos feitos com dados
                    falsos, por meios automatizados (robôs) ou para revenda
                    irregular, com reembolso do valor pago.
                </p>
            </LegalSection>

            <LegalSection title="7. Contato">
                <p>
                    Dúvidas, reclamações ou pedidos:{' '}
                    <SupportContact support={support} />.
                </p>
            </LegalSection>
        </LegalShell>
    );
}
