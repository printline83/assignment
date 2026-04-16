<div class="container py-5 mx-auto text-center" style="max-width: 600px;">
    <?php if ($status === 'success'): ?>
        <div class="card border-0 shadow-sm mt-5">
            <div class="card-body p-5">
                <div class="mb-4 text-success" style="font-size: 4rem;">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h3 class="fw-bold mb-3">결제가 완료되었습니다!</h3>
                <p class="text-muted mb-4 fs-5">예약 번호: <strong class="text-dark">#<?=$reservation_id?></strong></p>
                <div class="d-grid mt-4">
                    <button class="btn btn-dark btn-lg fw-bold" onclick="location.href='/main/products'">메인으로 돌아가기</button>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="card border-0 shadow-sm mt-5">
            <div class="card-body p-5">
                <div class="mb-4 text-danger" style="font-size: 4rem;">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <h3 class="fw-bold mb-3">결제에 실패했습니다</h3>
                <p class="text-muted mb-4 fs-5"><?=$message?></p>
                <div class="d-grid mt-4">
                    <button class="btn btn-outline-secondary btn-lg fw-bold" onclick="location.href='/main/products'">상품 목록으로 돌아가기</button>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
