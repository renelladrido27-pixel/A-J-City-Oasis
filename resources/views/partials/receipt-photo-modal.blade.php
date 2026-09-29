{{-- Shared viewer for utility bill receipt photos. Triggers: data-bs-toggle="modal" data-bs-target="#receiptModal" data-receipt-url="..." data-receipt-title="..." --}}
<div class="modal fade" id="receiptModal" tabindex="-1" aria-labelledby="receiptModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="receiptModalLabel">Receipt</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center bg-light">
                <img src="" alt="Utility bill receipt" class="img-fluid rounded" style="max-height: 75vh;" data-receipt-img>
            </div>
            <div class="modal-footer">
                <a href="#" target="_blank" rel="noopener" class="btn btn-outline-secondary" data-receipt-open><i class="bi bi-box-arrow-up-right me-1"></i>Open full size</a>
            </div>
        </div>
    </div>
</div>

<script>
    document.getElementById('receiptModal').addEventListener('show.bs.modal', function (e) {
        const trigger = e.relatedTarget;
        this.querySelector('[data-receipt-img]').src = trigger.dataset.receiptUrl;
        this.querySelector('[data-receipt-open]').href = trigger.dataset.receiptUrl;
        this.querySelector('.modal-title').textContent = trigger.dataset.receiptTitle;
    });
</script>
