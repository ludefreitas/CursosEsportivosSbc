# Configuração dos PDFs

Admin principal > Outras áreas > Arquivos PDF — logos e instituições.

Cada modelo possui logo principal e nome da instituição. Sem configuração, o documento reserva o espaço do logo e uma linha para o nome. O rodapé é opcional, com título livre (Apoio, Parceiros, Patrocinadores etc.) e até 12 logos, cada um com o respectivo nome. Arquivos aceitos: JPG ou PNG de 8 bits sem entrelaçamento, até 2 MB e 1.024 pixels por lado. A prévia usa dados fictícios e as alterações do formulário, sem salvar. A configuração aplica-se às próximas gerações, sem reescrever PDFs já baixados.

Modelos atuais: certificado de nível, declaração, chamada, lista com endereços, frequência mensal e grade de horários. A frequência e a grade usam a impressão/salvar PDF do navegador. Rodapés extensos podem ocupar páginas adicionais na grade. Arquivos enviados pelos usuários, como atestados, não são modelos gerados pelo sistema.

Configuração persistida em `config/pdf_branding.json`, com bloqueio de escrita e leitura. Imagens em `public/uploads/pdf-branding`. Preservar ambos nos backups e nas publicações. Não há migração de banco ou alterações de estrutura no fluxo HTTP.

## Novos documentos

- Centralizar geradores nativos em `app/Services/*PdfService.php`. Eles entram automaticamente no catálogo administrativo.
- Incluir `// @pdf-model identificador|Título em português` no gerador. Usar esse identificador em `PdfBrandingSettings::get()` e `PdfBrandingService::apply()`, reservando cabeçalho e rodapé na paginação. O serviço de identidade visual processa somente os PDFs simples produzidos pelos geradores do projeto, não PDFs arbitrários ou comprimidos de bibliotecas externas.
- Para impressão HTML, incluir o mesmo marcador e os parciais `pdf_branding_header.php` e `pdf_branding_footer.php`, com `$pdfModel` definido. Páginas PHP com `window.print()` ou `data-grid-print` entram automaticamente no catálogo. Outros mecanismos de impressão precisam declarar seu modelo no catálogo central.
- Adicionar a prévia com dados fictícios ao `PdfBrandingPreviewService`, usando o renderizador real. Novos modelos sem prévia específica recebem uma amostra de identidade visual; a integração do layout real continua obrigatória antes da entrega.
- Conferir paginação, nomes longos, transparência e links existentes. Rodapés não podem encobrir os dados do documento.

## Verificação

`php tests/pdf_branding_test.php` valida catálogo, prévias dos seis modelos, links da declaração e caminhos de imagens. A opção de diretório de saída é destinada à conferência visual com a imagem de teste preparada localmente. Testes HTTP foram realizados em ambiente isolado, com autenticação simulada e configuração separada, sem banco real.

`php tests/pdf_registry_discovery_test.php` confirma a entrada automática de novos geradores e páginas de impressão, usando arquivos fictícios em pasta temporária isolada.
