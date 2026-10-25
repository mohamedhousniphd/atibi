/* TP Planner - Global JS */
document.addEventListener('DOMContentLoaded', function() {
    // Auto-hide alerts after 5s
    document.querySelectorAll('.alert-dismissible').forEach(function(alert) {
        setTimeout(function() {
            var b = new bootstrap.Alert(alert);
            b.close();
        }, 5000);
    });

    // Confirm delete
    document.querySelectorAll('[data-confirm]').forEach(function(el) {
        el.addEventListener('click', function(e) {
            if (!confirm(this.getAttribute('data-confirm') || 'Are you sure?')) e.preventDefault();
        });
    });

    document.querySelectorAll('form[data-confirm]').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            var msg = form.getAttribute('data-confirm');
            if (msg && !confirm(msg)) e.preventDefault();
        });
    });

    // Tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(el) { return new bootstrap.Tooltip(el); });
});
