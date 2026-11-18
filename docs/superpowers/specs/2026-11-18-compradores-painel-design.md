# Compradores no painel: lista de contas e desvincular CPF

## Por que

O CPF é único entre as contas de comprador (`users`). CPFs usados em testes de fluxo ficam presos em contas antigas e barram o cadastro real ("Esse CPF já está em outra conta"). Hoje o painel não lista compradores; só dá para resolver mexendo direto no banco.

## Objetivo

- Ver todas as contas de comprador no painel.
- Achar a conta dona de um CPF e desvincular o CPF dela.

**Fora do escopo:** apagar conta, trocar e-mail, juntar contas, editar dados do comprador.
**Anti-objetivo:** expor CPF completo na tela.

## Abordagens consideradas

1. **Página "Compradores" com lista, busca e desvincular** (escolhida). Atende a visão geral e o caso do CPF preso, com ação de baixo risco.
2. Só busca por CPF, sem lista. Menos exposição, mas não dá a visão geral pedida.
3. Página com apagar, trocar e-mail e juntar contas. Mais poder, mas exige tratar pedidos e ingressos pendurados; trabalho bem maior.

## Design

- **Rota:** `GET /painel/compradores` e `DELETE /painel/compradores/{user}/cpf`, no grupo `can-organize` (staff não acessa). Item "Compradores" no `AdminSidebar`.
- **Lista paginada:** nome, e-mail, CPF mascarado mostrando só os 3 últimos dígitos (`•••.•••.••7-89`, ou "sem CPF"), data de cadastro, nº de pedidos. Mais recentes primeiro.
- **Busca:** um campo. Se forem 11 dígitos (com ou sem pontuação), busca CPF exato; senão, nome ou e-mail por trecho.
- **Desvincular CPF:** botão por conta com CPF. Modal de confirmação mostrando o nome e o e-mail da conta; se houver pedidos pagos, avisa a quantidade. Ao confirmar, `cpf = null` e toast de sucesso.
- **Registro:** `Log::info` com quem do painel desvinculou, a conta e a data (escolha minha: não existe tabela de auditoria no projeto e não vale criar uma só para isso).
- O CPF completo nunca sai do servidor; o mascaramento é feito no backend.

## Fica para quem implementar

- Componentes e tokens do painel existente (seguir `team.tsx` como referência).
- Testes de feature: acesso (staff 403), busca por CPF, máscara, desvincular.
