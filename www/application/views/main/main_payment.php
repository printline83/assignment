<div class="container py-4 mx-auto" style="max-width: 600px;">
    <h3 class="mb-4 fw-bold text-center">안전 결제</h3>
    
    <div class="card border-0 shadow-sm">
        <div class="card-body p-4 text-center">
            <h5 class="mb-3 text-muted">예약 번호: #<?=$reservation_id?></h5>
            <p class="mb-4">결제 정보를 안전하게 확인 중입니다.</p>
            
            <form id="paymentForm">
                <input type="hidden" id="paymentReservationId" value="<?=$reservation_id?>">
                <input type="hidden" id="paymentAmount" value="<?=$amount?>">
                <input type="hidden" id="paymentOrderName" value="<?=$order_name?>">
                <input type="hidden" id="paymentUserName" value="<?=$user_name?>">
                <!-- IdempotencyKey 로 중복 방지 -->
                <input type="hidden" id="idempotencyKey" value="">
                
                <div class="d-grid mt-4 gap-2">
                    <button type="submit" class="btn btn-dark btn-lg fw-bold">가상 카드로 결제 진행</button>
                    <button type="button" class="btn btn-outline-secondary btn-lg fw-bold" id="btnCancelPayment">결제 포기</button>
                </div>
            </form>
        </div>
    </div>
</div>
