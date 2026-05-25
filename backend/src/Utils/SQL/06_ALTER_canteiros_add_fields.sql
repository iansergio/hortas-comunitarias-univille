-- Migration: Add new fields to canteiros table for RF01-RF08
-- Features: Status, Localizacao, Plantio Atual, Data Ultima Colheita

ALTER TABLE canteiros ADD COLUMN status VARCHAR(20) DEFAULT 'Disponível' AFTER numero_identificador;
ALTER TABLE canteiros ADD COLUMN localizacao VARCHAR(100) NULL AFTER status;
ALTER TABLE canteiros ADD COLUMN plantio_atual VARCHAR(255) NULL AFTER localizacao;
ALTER TABLE canteiros ADD COLUMN data_ultima_colheita DATE NULL AFTER plantio_atual;

-- Add index for status filtering
CREATE INDEX idx_canteiros_status ON canteiros(status);
