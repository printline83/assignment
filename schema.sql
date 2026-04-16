-- 데이터베이스 스키마 정의 (MySQL / MariaDB)

CREATE TABLE `t_Products` (
  `f_ProductId` int(11) NOT NULL AUTO_INCREMENT COMMENT '상품번호',
  `f_ProductName` varchar(255) NOT NULL COMMENT '상품명',
  `f_Price` int(11) NOT NULL COMMENT '상품가격',
  `f_Stock` int(11) NOT NULL DEFAULT 0 COMMENT '재고',
  `f_CreatedAt` timestamp NULL DEFAULT current_timestamp() COMMENT '등록일시',
  `f_Delete` enum('Y','N') DEFAULT 'N' COMMENT '상품삭제여부',
  PRIMARY KEY (`f_ProductId`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci COMMENT='상품정보';

CREATE TABLE `t_Reservations` (
  `f_ReservationId` varchar(20) NOT NULL COMMENT '예약번호',
  `f_ProductId` int(11) NOT NULL COMMENT '상품번호',
  `f_ProductName` varchar(255) DEFAULT NULL COMMENT '상품명',
  `f_UserId` varchar(20) NOT NULL COMMENT '사용자아이디',
  `f_UserName` varchar(32) NOT NULL COMMENT '구매자명',
  `f_UserPhone` varchar(20) NOT NULL COMMENT '고객 휴대폰 번호',
  `f_Amount` int(11) DEFAULT NULL COMMENT '예약금액',
  `f_Status` enum('ED','CF','CX','EX') DEFAULT 'ED' COMMENT '예약상태 (ED:대기, CF:완료, CX:취소, EX:만료)',
  `f_CreatedAt` timestamp NULL DEFAULT current_timestamp() COMMENT '예약일시',
  `f_ExpiredAt` timestamp NOT NULL COMMENT '만료일시',
  PRIMARY KEY (`f_ReservationId`),
  KEY `f_ProductId` (`f_ProductId`),
  KEY `idx_status_expire` (`f_Status`,`f_ExpiredAt`),
  CONSTRAINT `fk_res_product` FOREIGN KEY (`f_ProductId`) REFERENCES `t_Products` (`f_ProductId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci COMMENT='예약정보';

CREATE TABLE `t_Payments` (
  `f_PaymentId` int(11) NOT NULL AUTO_INCREMENT COMMENT '결제 고유 번호 (PK)',
  `f_ReservationId` varchar(20) NOT NULL COMMENT '예약번호',
  `f_Amount` int(11) NOT NULL COMMENT '결제금액',
  `f_Status` enum('SS','FL','CX') DEFAULT 'SS' COMMENT '결제상태 (SS:성공, FL:실패, CX:취소)',
  `f_TransactionId` varchar(255) NOT NULL COMMENT 'PG승인 번호 (중복방지키)',
  `f_CreatedAt` timestamp NULL DEFAULT current_timestamp() COMMENT '결제일시',
  PRIMARY KEY (`f_PaymentId`),
  UNIQUE KEY `idx_transaction` (`f_TransactionId`),
  KEY `f_ReservationId` (`f_ReservationId`),
  CONSTRAINT `fk_pay_res` FOREIGN KEY (`f_ReservationId`) REFERENCES `t_Reservations` (`f_ReservationId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci COMMENT='결제정보';

CREATE TABLE `t_Sessions` (
  `id` varchar(40) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `timestamp` int(10) unsigned NOT NULL DEFAULT 0,
  `data` text NOT NULL,
  PRIMARY KEY (`id`,`ip_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_uca1400_ai_ci ROW_FORMAT=COMPACT COMMENT='ci 세션';

INSERT INTO t_Products (f_ProductName, f_Price, f_Stock, f_Delete) VALUES 
('파텍 필립 노틸러스 5711/1A', 185000000, 1, 'N'),
('오데마 피게 로열 오크 ' , 92000000, 1, 'N'),
('롤렉스 데이토나 레인보우', 750000000, 1, 'N'),
('바쉐론 콘스탄틴 패트리모니', 45000000, 1, 'N'),
('리샤르 밀 RM 11-03', 420000000, 1, 'N');