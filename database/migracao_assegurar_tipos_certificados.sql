-- Garante que os tipos de certificado usados no envio de documentos existam.
-- Pode ser executada mais de uma vez sem duplicar registros.

INSERT INTO tipos_certificados (slug, nome, categoria) VALUES
    ('pcd', 'Pessoa com Deficiência', 'condicao'),
    ('plm', 'Pessoa com Laudo Médico de Doença', 'condicao'),
    ('pvs', 'Pessoa em Situação de Vulnerabilidade Social', 'condicao')
ON DUPLICATE KEY UPDATE
    nome = VALUES(nome),
    categoria = VALUES(categoria);

SELECT id, slug, nome, categoria
FROM tipos_certificados
WHERE slug IN ('pcd', 'plm', 'pvs')
ORDER BY slug ASC;
