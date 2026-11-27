// MINUTA: texto atualizado para o modelo com vários organizadores. Precisa de
// revisão jurídica antes de ir para produção.
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
                    Estes termos valem para quem compra ingressos e fotos no
                    Kena, plataforma operada por {company.name}
                    {company.document ? ` (${company.document})` : ''}. Ao
                    reservar assentos ou concluir uma compra, você concorda com
                    eles e com a{' '}
                    <Link
                        href="/privacidade"
                        className="text-foreground underline underline-offset-2"
                    >
                        Política de privacidade
                    </Link>
                    . Quem organiza eventos segue também os{' '}
                    <Link
                        href="/termos/organizadores"
                        className="text-foreground underline underline-offset-2"
                    >
                        Termos do organizador
                    </Link>
                    .
                </p>
            }
        >
            <LegalSection title="1. O papel do Kena e do organizador">
                <p>
                    O Kena é uma plataforma que reúne eventos de vários
                    organizadores. Cada evento é vendido pelo organizador
                    indicado na página do evento, que é o responsável pelo
                    conteúdo, pela data, pelo local, pela realização do evento e
                    pelo atendimento sobre ele. O Kena fornece a vitrine, o
                    checkout, os ingressos e o controle de entrada.
                </p>
                <p>
                    O pagamento é feito por meio do Mercado Pago e o valor é
                    destinado ao organizador do evento. O Kena recebe do
                    organizador uma taxa pelo uso da plataforma, que é
                    descontada do valor repassado a ele e não é cobrada de você.
                </p>
            </LegalSection>

            <LegalSection title="2. Conta e dados do comprador">
                <p>
                    Você pode comprar com uma conta (e-mail e senha ou Google)
                    ou informando nome, e-mail e CPF. Os dados precisam ser
                    verdadeiros e o e-mail precisa ser seu: é por ele que
                    enviamos os ingressos e o acesso à conta. A mesma conta vale
                    para eventos de qualquer organizador do Kena.
                </p>
                {policy.maxTickets !== null && (
                    <p>
                        Cada CPF pode adquirir até {policy.maxTickets} ingressos
                        por sessão, cortesias incluídas.
                    </p>
                )}
            </LegalSection>

            <LegalSection title="3. Reserva, preço e pagamento">
                <ul>
                    <li>
                        Os assentos escolhidos ficam reservados por{' '}
                        {policy.holdMinutes} minutos para concluir o pagamento.
                    </li>
                    <li>
                        No Pix, a reserva acompanha a validade do código. Se o
                        pagamento não for confirmado nesse prazo, a reserva é
                        cancelada e os assentos voltam à venda.
                    </li>
                    <li>
                        O preço exibido no checkout, antes da confirmação, é o
                        valor total cobrado. Não há taxa de serviço somada ao
                        preço do ingresso ou da foto.
                    </li>
                    <li>
                        Os pagamentos são processados pelo Mercado Pago. O Kena
                        não armazena dados de cartão. Na fatura do cartão pode
                        aparecer o nome do organizador do evento.
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
                        Compras feitas pela internet podem ser desistidas em até
                        7 dias, contados da compra, com devolução integral do
                        valor pago (art. 49 do Código de Defesa do Consumidor).
                        Peça ao organizador, pelo contato na página do evento,
                        ou ao Kena.
                    </li>
                    <li>
                        Você pode pedir o reembolso em Meus ingressos até{' '}
                        {policy.refundDeadlineHours} horas antes da sessão. O
                        estorno volta pelo mesmo meio de pagamento, no prazo do
                        Mercado Pago.
                    </li>
                    <li>
                        Pedidos com ingresso já transferido ou usado não são
                        reembolsados pela plataforma. Fora destas regras, o
                        pedido deve ser feito ao organizador, pelo contato
                        indicado na página do evento e no e-mail dos ingressos.
                    </li>
                    <li>
                        Se o evento for cancelado pelo organizador, todos os
                        pedidos são reembolsados integralmente. Em caso de
                        adiamento, os ingressos valem para a nova data, e quem
                        não puder comparecer pode pedir reembolso.
                    </li>
                    <li>
                        Isso não afasta os direitos previstos no Código de
                        Defesa do Consumidor. Se não conseguir resolver com o
                        organizador, fale com a gente.
                    </li>
                </ul>
            </LegalSection>

            <LegalSection title="6. Fotos do evento">
                <ul>
                    <li>
                        Quando o organizador publica uma galeria, as fotos podem
                        ser vistas em prévia com marca d’água e compradas
                        avulsas ou em pacote. O preço exibido é o valor total.
                    </li>
                    <li>
                        Após o pagamento aprovado, os arquivos originais ficam
                        disponíveis para download em Minhas fotos.
                    </li>
                    <li>
                        As fotos têm direitos autorais do titular indicado na
                        galeria. A compra permite o uso pessoal das fotos
                        adquiridas.
                    </li>
                    <li>
                        Responsáveis podem pedir, na própria galeria, que uma
                        criança seja retirada do filtro por rosto.
                    </li>
                </ul>
            </LegalSection>

            <LegalSection title="7. Uso indevido">
                <p>
                    Podemos cancelar reservas ou pedidos feitos com dados
                    falsos, por meios automatizados (robôs) ou para revenda
                    irregular, com reembolso do valor pago. Também podemos
                    suspender as vendas de um organizador que descumpra os
                    Termos do organizador.
                </p>
            </LegalSection>

            <LegalSection title="8. Contato">
                <p>
                    Sobre um evento (programação, local, reembolso fora do
                    prazo): fale com o organizador, pelo contato na página do
                    evento. Sobre a plataforma, sua conta ou se não conseguir
                    resolver com o organizador:{' '}
                    <SupportContact support={support} />.
                </p>
            </LegalSection>
        </LegalShell>
    );
}
