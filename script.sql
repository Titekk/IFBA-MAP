-- =====================================================================
--  Banco de dados - Mapa Energético IFBA Campus Ilhéus
--  Fontes:
--   * PLANTA_IFBA_ILHEUS ARCONDICIONADO.xls       -> planta 2D, blocos, salas e ar-condicionados
--   * Pontos_de_Energia_Escola(1).xlsx / Tabelas -> quantidades de pontos de energia por sala
--  Campos em branco nas tabelas originais recebem 'Não identificável'.
-- =====================================================================
DROP DATABASE IF EXISTS banco_IFBA;
CREATE DATABASE banco_IFBA CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE banco_IFBA;

CREATE TABLE bloco (
  codigo_bloco INT PRIMARY KEY AUTO_INCREMENT,
  nome VARCHAR(100) NOT NULL,
  descricao VARCHAR(500)
);

CREATE TABLE sala (
  codigo_sala INT PRIMARY KEY AUTO_INCREMENT,
  identificacao VARCHAR(50) NOT NULL UNIQUE,
  andar VARCHAR(30) DEFAULT 'Não identificável',
  tipo_uso VARCHAR(50) DEFAULT 'Não identificável',
  capacidade_pessoas VARCHAR(30) DEFAULT 'Não identificável',
  area VARCHAR(30) DEFAULT 'Não identificável',
  situacao VARCHAR(30) DEFAULT 'Não identificável',
  codigo_bloco INT NOT NULL,
  FOREIGN KEY (codigo_bloco) REFERENCES bloco(codigo_bloco)
);

CREATE TABLE ponto_energia (
  codigo_sala INT PRIMARY KEY,
  tomada VARCHAR(30) NOT NULL DEFAULT 'Não identificável',
  ar_condicionado VARCHAR(30) NOT NULL DEFAULT 'Não identificável',
  ventilador VARCHAR(30) NOT NULL DEFAULT 'Não identificável',
  televisao VARCHAR(30) NOT NULL DEFAULT 'Não identificável',
  lampadas VARCHAR(30) NOT NULL DEFAULT 'Não identificável',
  computador VARCHAR(30) NOT NULL DEFAULT 'Não identificável',
  FOREIGN KEY (codigo_sala) REFERENCES sala(codigo_sala) ON DELETE CASCADE
);

CREATE TABLE equipamento (
  codigo_equipamento INT PRIMARY KEY AUTO_INCREMENT,
  tipo VARCHAR(50) NOT NULL DEFAULT 'Ar condicionado',
  marca VARCHAR(50) DEFAULT 'Não identificável',
  modelo VARCHAR(50) DEFAULT 'Não identificável',
  numero_serie VARCHAR(50) DEFAULT 'Não identificável',
  capacidade_btu INT,
  consumo_kwh_mes DECIMAL(10,2),
  tipo_fluido VARCHAR(30) DEFAULT 'Não identificável',
  tensao_V VARCHAR(20) DEFAULT 'Não identificável',
  tecnologia VARCHAR(30) DEFAULT 'Não identificável',
  potencia VARCHAR(30) DEFAULT 'Não identificável',
  data_aquisicao DATE,
  inicio_garantia DATE,
  fim_garantia DATE,
  status VARCHAR(30) DEFAULT 'Operacional'
);

CREATE TABLE alocacao (
  codigo_alocacao INT PRIMARY KEY AUTO_INCREMENT,
  data_instalacao DATE,
  data_retirada DATE,
  observacao VARCHAR(300) DEFAULT 'Não identificável',
  codigo_sala INT NOT NULL,
  codigo_equipamento INT NOT NULL,
  FOREIGN KEY (codigo_sala) REFERENCES sala(codigo_sala) ON DELETE CASCADE,
  FOREIGN KEY (codigo_equipamento) REFERENCES equipamento(codigo_equipamento) ON DELETE CASCADE
);

CREATE TABLE usuario (
  codigo_usuario INT PRIMARY KEY AUTO_INCREMENT,
  nome VARCHAR(100) NOT NULL,
  email VARCHAR(100) NOT NULL UNIQUE,
  senha VARCHAR(200) NOT NULL,
  situacao VARCHAR(30) DEFAULT 'Ativo'
);

CREATE TABLE adm (
  codigo_usuario INT PRIMARY KEY,
  FOREIGN KEY (codigo_usuario) REFERENCES usuario(codigo_usuario) ON DELETE CASCADE
);

CREATE TABLE tecnico (
  codigo_usuario INT PRIMARY KEY,
  matricula VARCHAR(30) NOT NULL,
  telefone VARCHAR(20),
  FOREIGN KEY (codigo_usuario) REFERENCES usuario(codigo_usuario) ON DELETE CASCADE
);

CREATE TABLE tipo_manutencao (
  codigo_tipo_manutencao INT PRIMARY KEY AUTO_INCREMENT,
  descricao VARCHAR(150) NOT NULL,
  categoria VARCHAR(50)
);

CREATE TABLE manutencao (
  codigo_manutencao INT PRIMARY KEY AUTO_INCREMENT,
  data_manutencao DATE NOT NULL,
  descricao_servico TEXT,
  custo DECIMAL(10,2),
  status VARCHAR(30),
  codigo_equipamento INT NOT NULL,
  codigo_tipo_manutencao INT NOT NULL,
  codigo_usuario INT NOT NULL,
  FOREIGN KEY (codigo_equipamento) REFERENCES equipamento(codigo_equipamento),
  FOREIGN KEY (codigo_tipo_manutencao) REFERENCES tipo_manutencao(codigo_tipo_manutencao),
  FOREIGN KEY (codigo_usuario) REFERENCES tecnico(codigo_usuario)
);

INSERT INTO bloco (codigo_bloco, nome, descricao) VALUES
(1, '2º Pavilhão - 1º Andar', 'Salas de aula 15 a 22, banheiro, corredor e rampas superiores'),
(2, '2º Pavilhão - Térreo & Laboratórios', 'Laboratórios de Edificações, Seg. Trabalho, LABOMEC, Ciências Ambientais, Topografia, Matemática, Desenho, Informática, Manutenção e Redes'),
(3, 'Bloco Sala Central', 'Salas de aula 01 a 10'),
(4, 'Bloco Administrativo e Ensino Sul', 'Salas 11 a 14, Coordenações, CGTI, DAP, GGP, DOF, COTEP, Diretoria, Gabinete, CORES e DG'),
(5, 'Bloco de Serviços e Convivência', ' Depósito, Área de Serviço, Sala Adm e Parte Externa'),
(6, 'Complexo Esportivo / Ginásio', 'Ginásio com Arquibancadas, Dojo, Sala de Dança, Academia, Vestiários, Banheiros, Coord. e Transp.'),
(7, 'Bloco Anfiteatro e Biblioteca', 'Anfiteatro e Biblioteca');

INSERT INTO sala (codigo_sala, identificacao, andar, tipo_uso, codigo_bloco) VALUES
(1, 'SALA DE AULA 15', '1º Andar', 'Sala de aula', 1),
(2, 'SALA DE AULA 16', '1º Andar', 'Sala de aula', 1),
(3, 'SALA DE AULA 17', '1º Andar', 'Sala de aula', 1),
(4, 'SALA DE AULA 18', '1º Andar', 'Sala de aula', 1),
(5, 'SALA DE AULA 19', '1º Andar', 'Sala de aula', 1),
(6, 'SALA DE AULA 20', '1º Andar', 'Sala de aula', 1),
(7, 'SALA DE AULA 21', '1º Andar', 'Sala de aula', 1),
(8, 'SALA DE AULA 22', '1º Andar', 'Sala de aula', 1),
(9, 'LAB EDIF', 'Térreo', 'Laboratório', 2),
(10, 'LAB ST', 'Térreo', 'Laboratório', 2),
(11, 'LABOMEC', 'Térreo', 'Laboratório', 2),
(12, 'CIÊNCIAS AMBIENTAIS', 'Térreo', 'Laboratório', 2),
(13, 'LAB TOPOGRAFIA', 'Térreo', 'Laboratório', 2),
(14, 'LAB MATEMÁTICA', 'Térreo', 'Laboratório', 2),
(15, 'LAB DESENHO', 'Térreo', 'Laboratório', 2),
(16, 'LAB INFORMÁTICA 1', 'Térreo', 'Laboratório', 2),
(17, 'LAB INFORMÁTICA 2', 'Térreo', 'Laboratório', 2),
(18, 'LAB MANUTENÇÃO', 'Térreo', 'Laboratório', 2),
(19, 'LAB REDES', 'Térreo', 'Laboratório', 2),
(20, 'ASSISTÊNCIA DE ALUNOS', 'Térreo', 'Administrativo', 2),
(21, 'GRÊMIO', 'Térreo', 'Estudantil', 2),
(23, 'SALA DE AULA 2', 'Térreo', 'Sala de aula', 3),
(24, 'SALA DE AULA 3', 'Térreo', 'Sala de aula', 3),
(25, 'SALA DE AULA 4', 'Térreo', 'Sala de aula', 3),
(26, 'SALA DE AULA 5', 'Térreo', 'Sala de aula', 3),
(27, 'SALA DE AULA 6', 'Térreo', 'Sala de aula', 3),
(28, 'SALA DE AULA 7', 'Térreo', 'Sala de aula', 3),
(29, 'SALA DE AULA 8', 'Térreo', 'Sala de aula', 3),
(30, 'SALA DE AULA 9', 'Térreo', 'Sala de aula', 3),
(31, 'SALA DE AULA 10', 'Térreo', 'Sala de aula', 3),
(71, 'GINÁSIO', 'Térreo', 'Esportivo', 6),
(72, 'ACADEMIA', 'Térreo', 'Esportivo', 6),
(73, 'SALA DE DANÇA', 'Térreo', 'Esportivo', 6),
(74, 'DOJO', 'Térreo', 'Esportivo', 6),
(75, 'VESTIÁRIO MASC.', 'Térreo', 'Vestiário', 6),
(76, 'VESTIÁRIO FEM.', 'Térreo', 'Vestiário', 6),
(77, 'COORD.', 'Térreo', 'Administrativo', 6),
(78, 'TRANSP.', 'Térreo', 'Administrativo', 6),
(79, 'BANHEIRO MASC.', 'Térreo', 'Banheiro', 6),
(80, 'BANHEIRO FEM.', 'Térreo', 'Banheiro', 6),
(82, 'ANFITEATRO', 'Térreo', 'Eventos', 7),
(83, 'BIBLIOTECA', 'Térreo', 'Biblioteca', 7)
;

INSERT INTO ponto_energia (codigo_sala, tomada, ar_condicionado, ventilador, televisao, lampadas, computador) VALUES
(1, '4', '1 (AGRATTO)', '4', '1 (M-TV ROKU)', '8', 'Não identificável'),
(2, '4', '1 (AGRATTO)', '4', '1', '8', 'Não identificável'),
(3, '4', '1 (AGRATTO)', '4', '1', '8', 'Não identificável'),
(4, '4', '1 (AGRATTO)', '4', '1', '8', 'Não identificável'),
(5, '4', '1 (AGRATTO)', '3', '1', '8', 'Não identificável'),
(6, '4', '1 (GREE)', '3', '1', '8', 'Não identificável'),
(7, '4', '1 (ELGIN)', '3', '1', '8', 'Não identificável'),
(8, '4', '1 (ELGIN)', '3', '1', '8', 'Não identificável'),
(9, '4', '1', '2', 'Não identificável', '16', '1'),
(10, '0', '1 (GREE)', '1', '0', '8', '0'),
(11, 'Não identificável', '1 (TCL)', '0', '0', '8', '0'),
(12, '13', '1 (GREE)', '0', '0', '8', '0'),
(13, '0', '1 (ELGIN)', '0', '0', '8', '1'),
(14, '5', '1 (ELGIN)', '0', '0', '8', '0'),
(15, 'Não identificável', 'Não identificável', 'Não identificável', 'Não identificável', 'Não identificável', 'Não identificável'),
(16, '17', '1', '0', '1', '15', '31'),
(17, '16', '1', '0', '1', 'Não identificável', '31'),
(18, '18', '1', '0', '0', '14', '1'),
(19, '18', '1', '0', '1', '12', '31'),
(20, 'Não identificável', 'Não identificável', 'Não identificável', 'Não identificável', 'Não identificável', 'Não identificável'),
(21, '1', '0', '0', '0', '8', '1'),
(23, '8', '1', '3', '1', '35', 'Não identificável'),
(24, '5', '1', '3', '1', '35', 'Não identificável'),
(25, '6', '1', '3', '1', '33', 'Não identificável'),
(26, 'Não identificável', 'Não identificável', 'Não identificável', 'Não identificável', 'Não identificável', 'Não identificável'),
(27, '18', '1', '3', '1', '16', 'Não identificável'),
(28, 'Não identificável', 'Não identificável', 'Não identificável', 'Não identificável', 'Não identificável', 'Não identificável'),
(29, '7', '1', '3', '1', '14', 'Não identificável'),
(30, '8', '1', '3', '1', '36', 'Não identificável'),
(31, '8', '1', '2', '1', '35', 'Não identificável'),
(71, 'Não identificável', 'Não identificável', 'Não identificável', 'Não identificável', 'Não identificável', 'Não identificável'),
(72, 'Não identificável', 'Não identificável', 'Não identificável', 'Não identificável', 'Não identificável', 'Não identificável'),
(73, '8', '0', '2', 'Não identificável', '8', '0'),
(74, '8', '0', 'Não identificável', 'Não identificável', '11', '0'),
(75, 'Não identificável', 'Não identificável', 'Não identificável', 'Não identificável', 'Não identificável', 'Não identificável'),
(76, 'Não identificável', '0', '0', 'Não identificável', '8', '0'),
(77, 'Não identificável', 'Não identificável', 'Não identificável', 'Não identificável', 'Não identificável', 'Não identificável'),
(78, 'Não identificável', 'Não identificável', 'Não identificável', 'Não identificável', 'Não identificável', 'Não identificável'),
(79, 'Não identificável', 'Não identificável', 'Não identificável', 'Não identificável', 'Não identificável', 'Não identificável'),
(80, 'Não identificável', 'Não identificável', 'Não identificável', 'Não identificável', 'Não identificável', 'Não identificável'),
(82, 'Não identificável', 'Não identificável', 'Não identificável', 'Não identificável', 'Não identificável', 'Não identificável'),
(83, '19', '3', '0', '0', '25', '5')
;

-- =====================================================================
--  DADOS: AR-CONDICIONADOS EXTRAÍDOS DA PLANTA E DAS TABELAS DAS SALAS
-- =====================================================================
CREATE TEMPORARY TABLE tmp_ac (sala VARCHAR(50), marca VARCHAR(50), consumo DECIMAL(10,2), btu INT);
INSERT INTO tmp_ac VALUES
('SALA DE AULA 22','ELGIN',64.1,30000),
('SALA DE AULA 21','ELGIN',64.1,30000),
('SALA DE AULA 20','GREE',88.2,36000),
('SALA DE AULA 19','AGRATTO',61.4,30000),
('SALA DE AULA 18','AGRATTO',61.4,30000),
('SALA DE AULA 17','AGRATTO',61.4,30000),
('SALA DE AULA 16','AGRATTO',61.4,30000),
('SALA DE AULA 15','AGRATTO',61.4,30000),
('SALA DE AULA 2','AGRATTO',61.4,30000),
('SALA DE AULA 3','GREE',88.2,36000),
('SALA DE AULA 4','GREE',88.2,36000),
('SALA DE AULA 6','AGRATTO',61.4,30000),
('SALA DE AULA 7','GREE',88.2,36000),
('SALA DE AULA 8','GREE',88.2,36000),
('SALA DE AULA 9','GREE',88.2,36000),
('SALA DE AULA 10','AGRATTO',61.4,30000),
('LABOMEC','TCL',61.4,30000),
('CIÊNCIAS AMBIENTAIS','GREE',61.4,30000),
('LAB ST','GREE',61.4,30000),
('LAB TOPOGRAFIA','ELGIN',64.1,30000),
('LAB MATEMÁTICA','ELGIN',64.1,30000),
('LAB EDIF','Não identificável',61.4,30000),
('LAB REDES','Não identificável',61.4,30000),
('LAB INFORMÁTICA 1','Não identificável',61.4,30000),
('LAB INFORMÁTICA 2','Não identificável',61.4,30000),
('LAB MANUTENÇÃO','Não identificável',61.4,30000),
('BIBLIOTECA','Não identificável',61.4,30000),
('BIBLIOTECA','Não identificável',61.4,30000),
('BIBLIOTECA','Não identificável',61.4,30000)
;

INSERT INTO equipamento (tipo, marca, capacidade_btu, consumo_kwh_mes, numero_serie, status)
SELECT 'Ar condicionado', marca, btu, consumo, CONCAT('Não identificável#', sala), 'Operacional' FROM tmp_ac;

INSERT INTO alocacao (data_instalacao, codigo_sala, codigo_equipamento, observacao)
SELECT CURDATE(), s.codigo_sala, e.codigo_equipamento, 'Instalado conforme levantamento da instituição'
FROM equipamento e
JOIN sala s ON s.identificacao = SUBSTRING_INDEX(e.numero_serie, '#', -1);

UPDATE equipamento SET numero_serie = 'Não identificável';
DROP TEMPORARY TABLE tmp_ac;

-- =====================================================================
--  DADOS: USUÁRIOS E CARGOS (Administrador, Técnico e Usuário)
-- =====================================================================
INSERT INTO usuario (codigo_usuario, nome, email, senha, situacao) VALUES
(1, 'Administrador IFBA', 'admin@ifba.edu.br', '$2y$10$4mxkFFlweFzjq5KAaZ7ff.8df.cPfxMsnk4uXXCRKRdqMnaDNVEBW', 'Ativo'),
(2, 'Carlos Técnico', 'tecnico@ifba.edu.br', '$2y$10$4mxkFFlweFzjq5KAaZ7ff.8df.cPfxMsnk4uXXCRKRdqMnaDNVEBW', 'Ativo'),
(3, 'Visitante / Aluno', 'usuario@ifba.edu.br', '$2y$10$4mxkFFlweFzjq5KAaZ7ff.8df.cPfxMsnk4uXXCRKRdqMnaDNVEBW', 'Ativo');

INSERT INTO adm (codigo_usuario) VALUES (1);
INSERT INTO tecnico (codigo_usuario, matricula, telefone) VALUES (2, 'TEC-2026-01', '(73) 99999-0000');

INSERT INTO tipo_manutencao (codigo_tipo_manutencao, descricao, categoria) VALUES
(1, 'Limpeza de filtros e higienização', 'Preventiva'),
(2, 'Higienização completa da evaporadora', 'Preventiva'),
(3, 'Recarga de fluido refrigerante', 'Corretiva'),
(4, 'Troca de capacitor / componente', 'Corretiva'),
(5, 'Reparo na placa ou rede elétrica', 'Corretiva'),
(6, 'Instalação / Remanejamento', 'Instalação');

INSERT INTO manutencao (data_manutencao, descricao_servico, custo, status, codigo_equipamento, codigo_tipo_manutencao, codigo_usuario) VALUES
(CURDATE(), 'Limpeza preventiva dos filtros e verificação de drenagem do aparelho Elgin 30.000 BTUs.', 120.00, 'Concluída', 1, 1, 2),
(CURDATE(), 'Inspeção de rotina e medição de rendimento térmico do aparelho Gree 36.000 BTUs.', 150.00, 'Em andamento', 3, 2, 2);
