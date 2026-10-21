# Cartazes de divulgação

Acesso: Admin > Cartaz de divulgação (`/admin/cartazes`). Disponível para administrador e administrador principal.

Cada campanha mantém o modelo visual comum a todos os locais. Escolher outro local/temporada preenche título, subtítulo, endereço e modalidades das turmas ativas. Cores, imagens e textos gerais são mantidos. Os textos podem ser ajustados manualmente. O contato/WhatsApp é informado pelo editor.

- **Salvar campanha** atualiza a campanha e mantém a versão anterior no histórico.
- **Criar nova campanha a partir desta** guarda uma campanha independente, preservando a original.
- **Iniciar campanha em branco** prepara outra criação sem apagar as existentes.
- **Histórico desta campanha** carrega uma versão antiga; salvá-la cria uma nova versão.
- **Pré-visualizar** mostra o resultado atual, inclusive alterações ainda não salvas.

Fotos opcionais (0–4) usam recortes inclinados, com posição horizontal, vertical e zoom independentes. Logos adicionais e fundos são opcionais. As imagens originais não são alteradas.

O endereço escrito no cartaz é um texto livre e opcional (por exemplo, www.cursosesportivossbc.com). Ele não altera o QR code. O QR é preenchido automaticamente com a URL deste sistema e inclui `?local_modalidades=ID#home-training-locations`, abrindo a modal das modalidades do local escolhido. O destino aparece em um campo somente de leitura e pode ser conferido por um link. URLs relativas da configuração são resolvidas com o domínio atual do navegador.

Saídas: PNG para celular (1080 pixels de largura), A4 (2480 pixels) e A3 (3508 pixels); compartilhamento de arquivo nativo em navegadores compatíveis, com download como alternativa. Impressão/salvar PDF usa o navegador; escolher o mesmo papel e desativar cabeçalhos/rodapés do navegador. O modelo `cartaz` aparece em Admin > Outras áreas > Arquivos PDF e usa logo, instituição e parceiros centrais. A prévia central usa o renderizador real, com dados fictícios.

## Armazenamento e publicação

Preservar **config/cartazes.json** e **public/uploads/posters/** no backup e na publicação. São arquivos de dados, ignorados pelo Git. A pasta config deve permitir gravação pelo PHP. Não é necessária migração de banco. As gravações usam bloqueio de arquivo, e versões antigas continuam disponíveis. Imagens enviadas e não utilizadas não são removidas automaticamente, para preservar campanhas e versões anteriores.

Biblioteca de QR: Kazuhiko Arase, `qrcode-generator`, licença MIT em `public/assets/js/vendor-qrcode.LICENSE.txt`. Fonte: https://github.com/kazuhikoarase/qrcode-generator (arquivo js/dist/qrcode.js obtido em 21/10/2026). Nenhuma chamada externa é feita na geração.

## Validação

- `php tests/poster_service_test.php`: gravação, edição, versões, duplicação, enquadramento e entradas inválidas.
- `php tests/pdf_branding_test.php` e `php tests/pdf_registry_discovery_test.php`: catálogo e prévia central.
- Verificação em Chrome: prévia, alteração do site, leitura do QR exportado, quatro imagens, enquadramento, PNG e impressão A4/A3 em uma página.
