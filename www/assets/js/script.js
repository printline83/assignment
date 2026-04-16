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
            const btnHtml = isSoldOut 
                ? `<button class="btn btn-secondary btn-reserve" disabled>품절</button>`
                : `<button class="btn btn-primary btn-reserve" onclick="location.href='/main/reserve/${p.ProductId}'">예약하기</button>`;
            
            const stockHtml = isSoldOut 
                ? `<span class="text-danger"><i class="fas fa-times-circle"></i> 품절</span>` 
                : `<span><i class="fas fa-box"></i> 남은 재고: ${p.Stock}개</span>`;

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

     
 
});

