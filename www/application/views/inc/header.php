<!DOCTYPE html>
<html lang="ko">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?=$page_title?></title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@300;400;500;600;700&family=Noto+Serif+KR:wght@400;500;600&display=swap" />
    <link rel="stylesheet" href="<?=AST?>css/style.min.css?v=<?=DATE?>" />
    <script src="https://js.tosspayments.com/v1/payment"></script>
</head>

<body>

    <div class="page_loader">
        <div class="box">
            <div class="spinner-border text-success" role="status">
                <span class="sr-only">Loading...</span>
            </div>
        </div>
    </div>