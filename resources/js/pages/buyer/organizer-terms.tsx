// MINUTA: termos de quem vende no Kena (aceitos no cadastro de organizador).
// Precisa de revisão jurídica antes de ir para produção.
import { Link } from '@inertiajs/react';
import {
    LegalSection,
    LegalShell,
    SupportContact,
} from '@/components/organisms/LegalPage';
import type { LegalPageProps } from '@/components/organisms/LegalPage';

const pct = (value: number) =>
    `${value.toLocaleString('pt-BR', { maximumFractionDigits: 2 })}%`;

export default function OrganizerTerms({
    company,
    support,
    policy,
    fees,
    updatedAt,
}: LegalPageProps) {
    return (
        <LegalShell
            title="Termos do organizador"
            updatedAt={updatedAt}
            intro={
                <p>
                    Estes termos valem para quem cria conta de organizador e
                    vende ingressos ou fotos no Kena, plataforma operada por{' '}
                    {company.name}
                    {company.document ? ` (${company.document})` : ''}. Eles
                    complementam os{' '}
                    <Link
                        href="/termos"
                        className="text-foreground underline underline-offset-2"
                    >
                        Termos de uso
                    </Link>{' '}
                    e a{' '}
                    <Link
                        href="/privacidade"
                        className="text-foreground underline underline-offset-2"
                    >
                        Política de privacidade
                    </Link>
                    , que valem para os seus compradores.
                </p>
            }
        >
            <LegalSection title="1. Conta e responsável">
                <ul>
                    <li>
                        A conta é criada com os dados do organizador (pessoa ou
                        produtora) e de quem a administra, que precisam ser
                        verdadeiros e mantidos atualizados.
                    </li>
                    <li>
                        Você responde pelo acesso ao painel e pelas pessoas da
                        sua equipe que cadastrar.
                    </li>
                </ul>
            </LegalSection>

            <LegalSection title="2. Responsabilidade pelo evento">
                <ul>
                    <li>
                        Você é o responsável pelo evento: conteúdo, data, local,
                        lotação, segurança, alvarás e autorizações, direitos
                        autorais e de imagem, e pela realização do evento como
                        anunciado.
                    </li>
                    <li>
                        Você atende o público sobre o evento e deve manter um
                        e-mail de contato atualizado nas configurações do
                        painel. Ele aparece na página do evento e no e-mail dos
                        ingressos.
                    </li>
                </ul>
            </LegalSection>

            <LegalSection title="3. Recebimento pelo Mercado Pago">
                <ul>
                    <li>
                        Para vender, você conecta a sua própria conta do Mercado
                        Pago. Os pagamentos dos compradores são feitos nessa
                        conta e seguem os termos e prazos do Mercado Pago,
                        inclusive as tarifas dele.
                    </li>
                    <li>
                        A taxa do Kena é descontada automaticamente de cada
                        pagamento e não é cobrada do comprador:{' '}
                        {pct(fees.tickets)} sobre o valor pago nos ingressos e{' '}
                        {pct(fees.photos)} sobre o valor pago nas fotos, salvo
                        condição negociada por escrito.
                    </li>
                    <li>
                        Desconectar a conta interrompe as vendas novas até você
                        conectar outra.
                    </li>
                </ul>
            </LegalSection>

            <LegalSection title="4. Reembolsos, cancelamentos e contestações">
                <ul>
                    <li>
                        O comprador pode pedir reembolso pela plataforma até{' '}
                        {policy.refundDeadlineHours} horas antes da sessão. O
                        estorno sai da sua conta do Mercado Pago.
                    </li>
                    <li>
                        Se o evento for cancelado, você deve reembolsar
                        integralmente todos os compradores. Em caso de
                        adiamento, os ingressos valem para a nova data e quem
                        não puder comparecer tem direito ao reembolso.
                    </li>
                    <li>
                        Você atende os pedidos de desistência feitos em até 7
                        dias da compra (art. 49 do Código de Defesa do
                        Consumidor), com devolução integral.
                    </li>
                    <li>
                        Contestações de compra no cartão (chargeback) são
                        tratadas pelo Mercado Pago com a sua conta.
                    </li>
                    <li>
                        Estas regras não afastam os direitos do consumidor
                        previstos no Código de Defesa do Consumidor.
                    </li>
                </ul>
            </LegalSection>

            <LegalSection title="5. Dados dos compradores">
                <ul>
                    <li>
                        Você recebe pelo painel os dados necessários para
                        organizar o evento e controlar a entrada (por exemplo,
                        nome e e-mail dos titulares, assentos e check-in).
                    </li>
                    <li>
                        Esses dados só podem ser usados para o evento e para o
                        atendimento aos compradores, com segurança e conforme a
                        Lei Geral de Proteção de Dados (Lei 13.709/2018). Não
                        podem ser vendidos nem usados para outra finalidade sem
                        base legal.
                    </li>
                    <li>
                        Nas galerias de fotos, você garante ter as autorizações
                        de imagem necessárias, em especial de crianças e
                        adolescentes, e deve atender os pedidos de retirada
                        feitos pelos responsáveis.
                    </li>
                </ul>
            </LegalSection>

            <LegalSection title="6. Suspensão e encerramento">
                <ul>
                    <li>
                        O Kena pode suspender as vendas de um organizador em
                        caso de denúncia, indício de fraude ou descumprimento
                        destes termos. Durante a suspensão, os eventos saem da
                        vitrine, e o painel continua acessível para ver pedidos
                        e fazer reembolsos.
                    </li>
                    <li>
                        A suspensão não afasta a sua responsabilidade pelos
                        eventos já vendidos e pelos reembolsos devidos.
                    </li>
                    <li>
                        Se o Kena tiver de pagar a um comprador ou a terceiros
                        por fato do seu evento (por exemplo, cancelamento sem
                        reembolso), você deve ressarcir o Kena desse valor.
                    </li>
                </ul>
            </LegalSection>

            <LegalSection title="7. Contato">
                <p>
                    Dúvidas sobre estes termos ou sobre a plataforma:{' '}
                    <SupportContact support={support} />.
                </p>
            </LegalSection>
        </LegalShell>
    );
}
