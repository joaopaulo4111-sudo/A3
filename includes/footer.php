<?php
/**
 * ============================================================
 *  Magnata Automóveis - Rodapé padrão das páginas internas
 *  Arquivo: includes/footer.php
 * ============================================================
 *  Fecha as tags abertas no header.php, inclui o modal de
 *  confirmação reutilizável e carrega os scripts.
 * ============================================================
 */
?>
        </main>

        <footer class="app-footer">
            <span>&copy; <?= date('Y') ?> <strong>Magnata Automóveis</strong> &mdash; Gestão de Concessionária Premium</span>
            <span class="d-none d-md-inline text-muted">Projeto Acadêmico A3 &bull; ADS</span>
        </footer>

    </div><!-- /.app-main -->
</div><!-- /.app-wrapper -->

<!-- Modal de confirmação reutilizável -->
<div class="modal fade" id="modalConfirmar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title"><i class="bi bi-exclamation-triangle-fill text-warning me-2"></i>Confirmação</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body" id="modalConfirmarTexto">Deseja realmente continuar?</div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="modalConfirmarBotao">Confirmar</button>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap 5 e Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= url('assets/js/script.js') ?>"></script>
</body>
</html>
