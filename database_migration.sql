CREATE TABLE IF NOT EXISTS payment_methods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    is_active TINYINT(1) DEFAULT 1
);

-- panel metode pembayaran
INSERT INTO payment_methods (name) VALUES
('Tunai'),
('QRIS'),
('Transfer Bank'),
('Gojek'),
('Digital (Legacy)');

ALTER TABLE orders ADD COLUMN payment_method_id INT NULL;

UPDATE orders o
JOIN payment_methods pm ON pm.name = 'Tunai'
SET o.payment_method_id = pm.id
WHERE o.payment_method = 'Tunai';

UPDATE orders o
JOIN payment_methods pm ON pm.name = 'Digital (Legacy)'
SET o.payment_method_id = pm.id
WHERE o.payment_method = 'Digital';

ALTER TABLE orders ADD CONSTRAINT fk_orders_payment_method FOREIGN KEY (payment_method_id) REFERENCES payment_methods(id);
