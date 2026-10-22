CREATE DATABASE banco_IFBA;
USE banco_IFBA;

CREATE TABLE bloco (
  codigo_bloco INT PRIMARY KEY AUTO_INCREMENT,
  nome VARCHAR(100) NOT NULL,
  descricao VARCHAR(255)
);

CREATE TABLE sala (
  codigo_sala INT PRIMARY KEY AUTO_INCREMENT,
  identificacao VARCHAR(50) NOT NULL,
  andar INT,
  tipo_uso VARCHAR(50),
  capacidade_pessoas INT,
  area DECIMAL(8,2),
  situacao VARCHAR(30),
  codigo_bloco INT NOT NULL,
  FOREIGN KEY (codigo_bloco) REFERENCES bloco(codigo_bloco)
);

CREATE TABLE equipamento (
  codigo_equipamento INT PRIMARY KEY AUTO_INCREMENT,
  marca VARCHAR(50),
  modelo VARCHAR(50),
  numero_serie VARCHAR(50),
  capacidade_btu INT,
  tipo_fluido VARCHAR(30),
  tensao VARCHAR(20),
  tecnologia VARCHAR(30),
  potencia DECIMAL(10,2),
  data_aquisicao DATE,
  inicio_garantia DATE,
  fim_garantia DATE,
  status VARCHAR(30)
);

CREATE TABLE alocacao (
  codigo_alocacao INT PRIMARY KEY AUTO_INCREMENT,
  data_instalacao DATE NOT NULL,
  data_retirada DATE,
  observacao VARCHAR(255),
  codigo_sala INT NOT NULL,
  codigo_equipamento INT NOT NULL,
  FOREIGN KEY (codigo_sala) REFERENCES sala(codigo_sala),
  FOREIGN KEY (codigo_equipamento) REFERENCES equipamento(codigo_equipamento)
);

CREATE TABLE usuario (
  codigo_usuario INT PRIMARY KEY AUTO_INCREMENT,
  nome VARCHAR(100) NOT NULL,
  email VARCHAR(100) NOT NULL UNIQUE,
  senha VARCHAR(255) NOT NULL,
  situacao VARCHAR(30)
);

CREATE TABLE adm (
  codigo_usuario INT PRIMARY KEY,
  FOREIGN KEY (codigo_usuario) REFERENCES usuario(codigo_usuario)
);

CREATE TABLE tecnico (
  codigo_usuario INT PRIMARY KEY,
  matricula VARCHAR(30) NOT NULL,
  telefone VARCHAR(20),
  FOREIGN KEY (codigo_usuario) REFERENCES usuario(codigo_usuario)
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