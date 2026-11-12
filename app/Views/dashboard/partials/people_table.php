<div class="table-wrap">
    <table class="data-table">
        <thead>
            <tr>
                <th scope="col">Nome</th>
                <th scope="col">CPF</th>
                <th scope="col">Data de nascimento</th>
                <th scope="col">Dados</th>
                <th scope="col">Atestado clínico</th>
                <th scope="col">Atestado dermatológico</th>
                <th scope="col">PAR-Q</th>
                <th scope="col">Documentação por condição</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($peopleRows as $dependent) { ?>
                <?php require ROOT_PATH . '/app/Views/dashboard/partials/dependent_row.php'; ?>
            <?php } ?>
            <?php if ($peopleRows === []) { ?>
                <tr data-empty-dependents><td colspan="8">Nenhum dependente cadastrado.</td></tr>
            <?php } ?>
        </tbody>
    </table>
</div>
