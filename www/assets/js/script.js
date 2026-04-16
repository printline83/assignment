function comma(str) {
    return String(str).replace(/(\d)(?=(?:\d{3})+(?!\d))/g, '$1,');
}

function uncomma(str) {
    return String(str).replace(/[^\d]+/g, '');
}

function onlyNumber(obj) {
    obj.value = uncomma(obj.value);
}

$(document).ready(function() {
    if ($('#userPhone').length > 0) {
        $('#userPhone').on('input', function() {
            onlyNumber(this);
        });
    }

    if ($('#product-list').length > 0) {
        loadProducts();
    }

    function loadProducts() {
        $('.page_loader').addClass('loading');

        $.ajax({
            url: '/api/products',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                $('.page_loader').removeClass('loading');
                if (response.status === 'success') {
                    renderProducts(response.data);
                } else {
                    alert('상품을 불러오는 데 실패했습니다.');
                }
            },
            error: function() {
                $('.page_loader').removeClass('loading');
                alert('서버 통신 오류가 발생했습니다.');
            }
        });
    }

    function renderProducts(products) {
        const $list = $('#product-list');
        $list.empty();

        if (!products || products.length === 0) {
            $list.html('<div class="text-center text-muted py-5">등록된 상품이 없습니다.</div>');
            return;
        }

        products.forEach(function(p) {
            const isSoldOut = parseInt(p.Stock) <= 0;
            const isPending = parseInt(p.PendingCount) > 0;

            let btnHtml = '';
            let stockHtml = '';

            if (isSoldOut && isPending) {
                btnHtml = `<button class="btn btn-warning btn-reserve text-dark fw-bold" disabled>결제 진행중</button>`;
                stockHtml = `<span class="text-warning fw-bold"><i class="fas fa-hourglass-half"></i> 결제 진행중</span>`;
            } else if (isSoldOut) {
                btnHtml = `<button class="btn btn-secondary btn-reserve" disabled>품절</button>`;
                stockHtml = `<span class="text-danger"><i class="fas fa-times-circle"></i> 품절</span>`;
            } else {
                btnHtml = `<button class="btn btn-primary btn-reserve" onclick="location.href='/main/reserve/${p.ProductId}'">예약하기</button>`;
                stockHtml = `<span><i class="fas fa-box"></i> 남은 재고: ${p.Stock}개</span>`;
            }

            $list.append(`
              <div class="product-item">
                  <div class="product-details">
                      <div class="product-name">${p.ProductName}</div>
                      <div class="product-price">${comma(p.Price)}원</div>
                      <div class="product-stock">${stockHtml}</div>
                  </div>
                  <div class="product-action">
                      ${btnHtml}
                  </div>
              </div>
            `);
        });
    }

    const reserveProductId = $('#reserveProductId').val();
    if (reserveProductId) {
        $('.page_loader').addClass('loading');
        $.ajax({
            url: '/api/product/' + reserveProductId,
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                $('.page_loader').removeClass('loading');
                if (response.status === 'success') {
                    const product = response.data;
                    if (product) {
                        $('#reserveProductInfo').html(`
                            <div class="fw-bold mb-1 fs-5">${product.ProductName}</div>
                            <div class="text-primary fw-bold">${comma(product.Price)}원</div>
                        `);
                    } else {
                        alert('상품을 찾을 수 없습니다.');
                        location.href = '/main/products';
                    }
                }
            },
            error: function(xhr) {
                $('.page_loader').removeClass('loading');
                if (xhr.status === 404) {
                    alert('해당 상품을 찾을 수 없거나 품절되었습니다.');
                    location.href = '/main/products';
                } else {
                    alert('상품 정보를 불러오지 못했습니다.');
                }
            }
        });
    }

    $('#reservationForm').on('submit', function(e) {
        e.preventDefault();
        
        const productId = $('#reserveProductId').val();
        const userName = $('#userName').val();
        const userPhone = $('#userPhone').val();

        if (!userName || !userPhone) {
            alert('예약자 정보를 모두 입력해주세요.');
            return;
        }

        $('.page_loader').addClass('loading');

        $.ajax({
            url: '/api/reserve',
            type: 'POST',
            data: {
                productId: productId,
                userName: userName,
                userPhone: uncomma(userPhone)
            },
            dataType: 'json',
            success: function(response) {
                $('.page_loader').removeClass('loading');
                if (response.status === 'success') {
                    alert('예약이 접수되었습니다. 결제 페이지로 이동합니다.');
                    location.href = '/main/payment?reservation_id=' + response.data.v_ReservationId;
                }
            },
            error: function(xhr) {
                $('.page_loader').removeClass('loading');
                const res = xhr.responseJSON;
                if (res && res.message) {
                    alert(res.message);
                    location.href = '/main/products';
                } else {
                    alert('예약 처리 중 통신 오류가 발생했습니다.');
                    location.href = '/main/products';
                }
            }
        });
    });

    // Toss 결제창 호출 (main_payment.php 폼 이벤트)
    if ($('#paymentForm').length > 0) {
        $('#paymentForm').on('submit', function(e) {
            e.preventDefault();
            
            var reservationId = $('#paymentReservationId').val();
            var amount = $('#paymentAmount').val();
            var orderName = $('#paymentOrderName').val();
            var userName = $('#paymentUserName').val();

            $('.page_loader').addClass('loading');

            // 토스 결제창 띄우기 전, 서버에 결제 유효기간 및 상태 체크
            $.ajax({
                url: '/api/check_validity',
                type: 'POST',
                data: { reservation_id: reservationId },
                dataType: 'json',
                success: function(response) {
                    $('.page_loader').removeClass('loading');
                    
                    if (response.status === 'success') {
                        var tossPayments = TossPayments('test_ck_Z0RnYX2w532nKeAyllM3NeyqApQE');
                        tossPayments.requestPayment('카드', {
                            amount: amount,
                            orderId: reservationId,
                            orderName: orderName,
                            customerName: userName,
                            successUrl: window.location.origin + '/api/payment_success',
                            failUrl: window.location.origin + '/api/payment_fail'
                        }).catch(function(err) {
                            if (err.code === 'USER_CANCEL') {
                                alert('결제가 취소되었습니다.');
                            } else {
                                alert('결제창 호출 에러: ' + err.message);
                            }
                        });
                    }
                },
                error: function(xhr) {
                    $('.page_loader').removeClass('loading');
                    var msg = '예약 상태 확인 중 오류가 발생했습니다.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        msg = xhr.responseJSON.message;
                    }
                    alert(msg);
                    location.href = '/main/products';
                }
            });
        });

        // 결제 포기 버튼 이벤트
        $('#btnCancelPayment').on('click', function() {
            if (confirm('정말로 결제를 포기하시겠습니까? (예약 내역이 취소되어 다른 사람이 즉시 구매할 수 있게 됩니다.)')) {
                var reservationId = $('#paymentReservationId').val();
                
                $('.page_loader').addClass('loading');
                $.ajax({
                    url: '/api/cancel_payment',
                    type: 'POST',
                    data: { reservation_id: reservationId },
                    dataType: 'json',
                    success: function() {
                        $('.page_loader').removeClass('loading');
                        alert('결제가 포기되어 예약이 취소되었습니다.');
                        location.href = '/main/products';
                    },
                    error: function() {
                        $('.page_loader').removeClass('loading');
                        alert('오류가 발생했습니다.');
                        location.href = '/main/products';
                    }
                });
            }
        });
    }

});

// 결제 완료창 내 결제취소(환불) 로직
window.refundPayment = function(reservationId) {
    if (confirm('정말로 결제를 취소하시겠습니까? 토스 카드 전액 환불 및 주문이 취소됩니다.')) {
        $('.page_loader').addClass('loading');
        $.ajax({
            url: '/api/refund_payment',
            type: 'POST',
            data: { reservation_id: reservationId },
            dataType: 'json',
            success: function(response) {
                $('.page_loader').removeClass('loading');
                if (response.status === 'success') {
                    alert('결제가 성공적으로 취소/환불되었습니다.');
                    location.href = '/main/products';
                }
            },
            error: function(xhr) {
                $('.page_loader').removeClass('loading');
                var msg = '환불 처리 중 오류가 발생했습니다.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                alert(msg);
            }
        });
    }
};
