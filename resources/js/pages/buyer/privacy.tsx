import {
    LegalSection,
    LegalShell,
    SupportContact,
} from '@/components/organisms/LegalPage';
import type { LegalPageProps } from '@/components/organisms/LegalPage';

export default function Privacy({
    company,
    support,
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
                    Lei Geral de Proteção de Dados (Lei 13.709/2018). O
                    controlador dos dados é {company.name}
                    {company.document ? ` (${company.document})` : ''}.
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
                        nome dos portadores e lista de ingressos, para a
                        organização e o controle de entrada.
                    </li>
                    <li>
                        <strong className="text-foreground">
                            Mercado Pago:
                        </strong>{' '}
                        processamento dos pagamentos.
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
                    <SupportContact support={support} />.
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
