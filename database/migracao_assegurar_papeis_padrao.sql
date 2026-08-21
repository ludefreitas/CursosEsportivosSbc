-- Garante que os papéis base do sistema existam na base de dados real.
-- Execute este script no ambiente de produção, caso a tabela papeis esteja incompleta.

INSERT IGNORE INTO papeis (id, slug, nome) VALUES
    (1, 'master_admin', 'Administrador Master'),
    (2, 'admin', 'Administrador'),
    (3, 'supervisor', 'Supervisor'),
    (4, 'coordinator', 'Coordenador'),
    (5, 'teacher', 'Professor'),
    (6, 'intern', 'Estagiário');

SELECT id, slug, nome
FROM papeis
ORDER BY id ASC;
