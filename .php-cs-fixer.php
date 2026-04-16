<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->name('*.php')
    ->exclude('views')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        // PSR-2 코딩 표준을 적용합니다.
        '@PSR2'                                  => true,
        // 메서드 인자 사이의 공백을 조정합니다.
        'method_argument_space'                  => true,
        // PHP 파일의 닫는 태그를 제거합니다.
        'no_closing_tag'                         => true,
        // 줄 끝의 불필요한 공백을 제거합니다.
        'no_trailing_whitespace'                 => true,
        // 들여쓰기 타입을 강제합니다(스페이스 또는 탭).
        'indentation_type'                       => true,
        // 배열 안의 들여쓰기를 적용합니다.
        'array_indentation'                      => true,
        // PHPDoc 주석의 들여쓰기를 조정합니다.
        'phpdoc_indent'                          => true,
        // 논리 연산자를 적절히 사용하도록 수정합니다.
        'logical_operators'                      => true,
        // 배열에서 쉼표 다음에 공백을 추가합니다.
        'whitespace_after_comma_in_array'        => true,
        // 배열 요소 주변의 불필요한 공백을 제거합니다.
        'trim_array_spaces'                      => true,
        // 삼항 연산자 주변의 공백을 조정합니다.
        'ternary_operator_spaces'                => true,
        // switch 문의 case 뒤에 세미콜론 대신 콜론을 사용합니다.
        'switch_case_semicolon_to_colon'         => true,
        // switch 문의 case와 표현식 사이에 공백을 추가합니다.
        'switch_case_space'                      => true,
        // 세미콜론 앞의 여러 줄 공백을 허용하지 않습니다.
        'multiline_whitespace_before_semicolons' => false,
        // 세미콜론 뒤에 공백을 추가합니다.
        'space_after_semicolon'                  => true,
        // 문자열을 작은 따옴표로 감쌉니다.
        'single_quote'                           => true,
        // 특정 구문 앞에 빈 줄을 추가합니다.
        'blank_line_before_statement'            => true,
        // include 문을 적절히 사용하도록 수정합니다.
        'include'                                => true,
        // 캐스트 연산자를 소문자로 작성합니다.
        'lowercase_cast'                         => true,
        // 배열 오프셋 주변의 공백을 제거합니다.
        'no_spaces_around_offset'                => true,
        // 배열에서 쉼표 앞의 공백을 제거합니다.
        'no_whitespace_before_comma_in_array'    => true,
        // 단항 연산자(!, ~ 등) 앞뒤 공백 정리
        'unary_operator_spaces' => true,
        // 불필요한 빈 줄을 제거합니다.
        'no_extra_blank_lines'                   => [
            'tokens' => [
                'curly_brace_block',
                'extra',
                'throw',
                'use'
            ]
        ],
        // 문자열 연결 연산자 주변의 공백을 제거합니다.
        'concat_space'                           => ['spacing' => 'none'],
        // 클래스 내 메서드 사이에 한 줄만 빈 줄을 추가합니다.
        'class_attributes_separation'            => ['elements' => ['method' => 'one']],
        // 배열 문법을 짧은 문법으로 변경합니다.
        'array_syntax'                           => ['syntax' => 'short'],
        // 한 줄 주석 스타일을 지정합니다.
        'single_line_comment_style'              => ['comment_types' => ['asterisk', 'hash']],
        // 이항 연산자 주변의 공백을 조정합니다.
        'binary_operator_spaces'                 => [
            'default'   => 'single_space',
            'operators' => [
                '=>' => 'align_single_space',
                '='  => 'align_single_space',
            ],
        ],
        // 여러 줄 메서드 인자 사이의 공백을 보장합니다.
        'method_argument_space' => [
            'on_multiline' => 'ensure_fully_multiline',
        ],
    ])
    ->setFinder($finder);