<div class="container py-4 mx-auto max-w-600">
    <h3 class="mb-4 fw-bold text-center">상품 예약</h3>
    
    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <form id="reservationForm">
                <input type="hidden" id="reserveProductId" name="productId" value="<?=$product_id?>">
                
                <div class="mb-4 p-3 rounded bg-light border">
                    <div id="reserveProductInfo">
                        <div class="text-center text-muted">상품 정보를 불러오는 중입니다...</div>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="userName" class="form-label fw-bold">예약자 성함</label>
                    <input type="text" class="form-control form-control-lg" id="userName" name="userName" required placeholder="이름을 입력하세요">
                </div>
                
                <div class="mb-4">
                    <label for="userPhone" class="form-label fw-bold">연락처</label>
                    <input type="tel" class="form-control form-control-lg" id="userPhone" name="userPhone" required placeholder="01012345678">
                </div>
                
                <div class="d-grid mt-2 gap-2">
                    <button type="submit" class="btn btn-primary btn-lg fw-bold">결제 및 예약하기</button>
                    <button type="button" class="btn btn-outline-secondary btn-lg fw-bold" onclick="location.href='/main/products'">뒤로가기</button>
                </div>
            </form>
        </div>
    </div>
</div>
