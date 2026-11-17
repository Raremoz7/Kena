// MINUTA: atualizada para o modelo com vários organizadores e para as fotos.
// Precisa de revisão jurídica antes de ir para produção.
import {
    LegalSection,
    LegalShell,
    SupportContact,
} from '@/components/organisms/LegalPage';
import type { LegalPageProps } from '@/components/organisms/LegalPage';

export default function Privacy({
    company,
    support,
    dpo,
    updatedAt,
}: LegalPageProps) {
    return (
        <LegalShell
            title="Política de privacidade"
            updatedAt={updatedAt}
            intro={
                <p>
                    Esta política explica quais dados pessoais o Kena coleta,
                    por que e como você pode exercer seus direitos conforme a
                    Lei Geral de Proteção de Dados (Lei 13.709/2018). O Kena é
                    operado por {company.name}
                    {company.document ? ` (${company.document})` : ''}, que é
                    controladora dos dados da sua conta e das compras feitas na
                    plataforma. O organizador de cada evento recebe os dados
                    necessários para realizar o evento e responde pelo uso que
                    faz deles.
                </p>
            }
        >
            <LegalSection title="Dados que coletamos">
                <ul>
                    <li>
                        <strong className="text-foreground">
                            Cadastro e compra:
                        </strong>{' '}
                        nome, e-mail, CPF e, se você informar, telefone.
                    </li>
                    <li>
                        <strong className="text-foreground">
                            Login com Google:
                        </strong>{' '}
                        nome, e-mail e o identificador da sua conta Google. Não
                        recebemos sua senha.
                    </li>
                    <li>
                        <strong className="text-foreground">
                            Pedidos e ingressos:
                        </strong>{' '}
                        assentos, valores, status do pagamento, transferências e
                        registro de entrada no evento.
                    </li>
                    <li>
                        <strong className="text-foreground">Pagamento:</strong>{' '}
                        os dados do cartão são enviados direto ao Mercado Pago e
                        nunca ficam no Kena. Guardamos só o identificador e o
                        status do pagamento.
                    </li>
                    <li>
                        <strong className="text-foreground">
                            Fotos do evento:
                        </strong>{' '}
                        quando o organizador publica uma galeria, as fotos podem
                        trazer a sua imagem. O fotógrafo pode marcar o nome das
                        pessoas nas fotos, e o Kena usa essa marcação para
                        montar o filtro por rosto (um recorte do rosto e o
                        nome). Pedidos de retirada guardam o nome do
                        responsável, o contato e o nome da criança.
                    </li>
                    <li>
                        <strong className="text-foreground">Navegação:</strong>{' '}
                        endereço IP e cookies necessários para manter sua sessão
                        e a segurança do site. Não usamos cookies de
                        publicidade.
                    </li>
                </ul>
            </LegalSection>

            <LegalSection title="Para que usamos">
                <ul>
                    <li>
                        Emitir e enviar seus ingressos e permitir a entrada no
                        evento (execução do contrato).
                    </li>
                    <li>
                        Processar pagamentos, reembolsos e prevenir fraudes
                        (execução do contrato e legítimo interesse).
                    </li>
                    <li>
                        Enviar avisos sobre a sua compra: confirmação, lembrete,
                        alterações e cancelamento do evento.
                    </li>
                    <li>
                        Mostrar e vender as fotos do evento e permitir que cada
                        família encontre as suas pelo filtro por rosto.
                    </li>
                    <li>Cumprir obrigações legais e fiscais.</li>
                </ul>
                <p>
                    O CPF é pedido porque o Mercado Pago exige a identificação
                    do pagador.
                </p>
            </LegalSection>

            <LegalSection title="Com quem compartilhamos">
                <ul>
                    <li>
                        <strong className="text-foreground">
                            Organizador do evento:
                        </strong>{' '}
                        nome e e-mail dos titulares, assentos, pedidos e
                        registro de entrada, para a organização, o atendimento e
                        o controle de entrada do evento que você comprou. Cada
                        organizador só vê os dados dos próprios eventos.
                    </li>
                    <li>
                        <strong className="text-foreground">
                            Mercado Pago:
                        </strong>{' '}
                        processamento dos pagamentos, na conta do organizador do
                        evento. Os dados do pagador (nome, e-mail e CPF) são
                        enviados ao Mercado Pago para a cobrança.
                    </li>
                    <li>
                        <strong className="text-foreground">Brevo:</strong>{' '}
                        envio dos e-mails transacionais.
                    </li>
                    <li>
                        <strong className="text-foreground">Google:</strong>{' '}
                        apenas se você escolher entrar com Google.
                    </li>
                    <li>Autoridades, quando houver obrigação legal.</li>
                </ul>
                <p>Não vendemos seus dados.</p>
            </LegalSection>

            <LegalSection title="Por quanto tempo guardamos">
                <p>
                    Mantemos os dados da conta enquanto ela existir. Os
                    registros de pedidos e pagamentos são guardados pelo prazo
                    exigido pela legislação fiscal e de defesa do consumidor,
                    mesmo após a exclusão da conta.
                </p>
            </LegalSection>

            <LegalSection title="Seus direitos">
                <p>
                    Você pode pedir confirmação e acesso aos seus dados,
                    correção, anonimização, portabilidade, exclusão, informações
                    sobre compartilhamento e revogação de consentimento. Você
                    também pode excluir sua conta em Configurações. Para
                    qualquer pedido, fale com a gente:{' '}
                    <SupportContact support={support} />. Pedidos sobre o uso
                    que um organizador faz dos seus dados também podem ser
                    feitos diretamente a ele, pelo contato na página do evento.
                </p>
                <p>
                    Responsáveis podem pedir a retirada de uma criança do filtro
                    por rosto direto na galeria de fotos.
                </p>
            </LegalSection>

            <LegalSection title="Encarregado de dados">
                <p>
                    {dpo.name ? (
                        <>
                            O encarregado pelo tratamento de dados pessoais é{' '}
                            {dpo.name}
                            {dpo.email ? (
                                <>
                                    , pelo e-mail{' '}
                                    <a
                                        href={`mailto:${dpo.email}`}
                                        className="text-foreground underline underline-offset-2"
                                    >
                                        {dpo.email}
                                    </a>
                                </>
                            ) : null}
                            .
                        </>
                    ) : (
                        <>
                            Para assuntos de dados pessoais, fale com a gente:{' '}
                            <SupportContact support={support} />.
                        </>
                    )}
                </p>
            </LegalSection>

            <LegalSection title="Segurança">
                <p>
                    Usamos conexão criptografada (HTTPS), senhas com hash, QR
                    codes assinados e acesso restrito aos dados. Se houver um
                    incidente que afete seus dados, avisaremos você e a ANPD
                    conforme a lei.
                </p>
            </LegalSection>
        </LegalShell>
    );
}
