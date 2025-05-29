
-- Tabela de Usuários
CREATE TABLE IF NOT EXISTS `usuarios` (
    `id_usuario` INT AUTO_INCREMENT PRIMARY KEY,
    `nome_completo` VARCHAR(255) NOT NULL,
    `username` VARCHAR(100) NOT NULL UNIQUE,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `senha_hash` VARCHAR(255) NOT NULL COMMENT 'Hash da senha do usuário',
    `nivel_acesso` ENUM('Administrador', 'Técnico', 'Atendente') NOT NULL DEFAULT 'Atendente',
    `ativo` BOOLEAN NOT NULL DEFAULT TRUE,
    `data_cadastro` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `ultimo_login` DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Clientes
CREATE TABLE IF NOT EXISTS `clientes` (
    `id_cliente` INT AUTO_INCREMENT PRIMARY KEY,
    `nome` VARCHAR(255) NOT NULL,
    `cpf_cnpj` VARCHAR(20) UNIQUE COMMENT 'CPF ou CNPJ do cliente',
    `rg_ie` VARCHAR(20) NULL COMMENT 'RG ou Inscrição Estadual',
    `endereco` VARCHAR(255) NULL,
    `numero` VARCHAR(10) NULL,
    `complemento` VARCHAR(100) NULL,
    `bairro` VARCHAR(100) NULL,
    `cidade` VARCHAR(100) NULL,
    `estado` VARCHAR(2) NULL,
    `cep` VARCHAR(10) NULL,
    `telefone` VARCHAR(20) NULL,
    `celular` VARCHAR(20) NULL,
    `email` VARCHAR(255) NULL UNIQUE,
    `data_cadastro` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `observacoes` TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Serviços
CREATE TABLE IF NOT EXISTS `servicos` (
    `id_servico` INT AUTO_INCREMENT PRIMARY KEY,
    `nome_servico` VARCHAR(255) NOT NULL,
    `descricao` TEXT NULL,
    `preco` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `ativo` BOOLEAN NOT NULL DEFAULT TRUE,
    `data_cadastro` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Produtos
CREATE TABLE IF NOT EXISTS `produtos` (
    `id_produto` INT AUTO_INCREMENT PRIMARY KEY,
    `nome_produto` VARCHAR(255) NOT NULL,
    `descricao` TEXT NULL,
    `preco_venda` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `preco_custo` DECIMAL(10, 2) NULL DEFAULT 0.00,
    `estoque` INT NOT NULL DEFAULT 0,
    `unidade_medida` VARCHAR(50) NULL COMMENT 'Ex: Unidade, Metro, Litro, etc.',
    `data_cadastro` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Ordens de Serviço
CREATE TABLE IF NOT EXISTS `ordens_servico` (
    `id_os` INT AUTO_INCREMENT PRIMARY KEY,
    `id_cliente` INT NOT NULL,
    `data_abertura` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `data_fechamento` DATETIME NULL,
    `status` ENUM('Aberta', 'Em Andamento', 'Concluída', 'Cancelada', 'Aguardando Peças', 'Aguardando Aprovação') NOT NULL DEFAULT 'Aberta',
    `equipamento` VARCHAR(255) NULL COMMENT 'Nome ou tipo do equipamento',
    `numero_serie` VARCHAR(100) NULL COMMENT 'Número de série do equipamento',
    `defeito_relatado` TEXT NULL,
    `solucao_aplicada` TEXT NULL,
    `observacoes_tecnicas` TEXT NULL,
    `valor_total_servicos` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `valor_total_produtos` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `valor_desconto` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `valor_final` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `id_usuario_abertura` INT NOT NULL COMMENT 'Usuário que abriu a OS',
    `id_usuario_fechamento` INT NULL COMMENT 'Usuário que fechou a OS',
    FOREIGN KEY (`id_cliente`) REFERENCES `clientes`(`id_cliente`) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (`id_usuario_abertura`) REFERENCES `usuarios`(`id_usuario`) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (`id_usuario_fechamento`) REFERENCES `usuarios`(`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Itens de Serviço da OS
CREATE TABLE IF NOT EXISTS `os_servicos` (
    `id_os_servico` INT AUTO_INCREMENT PRIMARY KEY,
    `id_os` INT NOT NULL,
    `id_servico` INT NOT NULL,
    `quantidade` INT NOT NULL DEFAULT 1,
    `preco_unitario` DECIMAL(10, 2) NOT NULL,
    `subtotal` DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (`id_os`) REFERENCES `ordens_servico`(`id_os`) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (`id_servico`) REFERENCES `servicos`(`id_servico`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Itens de Produto da OS
CREATE TABLE IF NOT EXISTS `os_produtos` (
    `id_os_produto` INT AUTO_INCREMENT PRIMARY KEY,
    `id_os` INT NOT NULL,
    `id_produto` INT NOT NULL,
    `quantidade` INT NOT NULL DEFAULT 1,
    `preco_unitario` DECIMAL(10, 2) NOT NULL,
    `subtotal` DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (`id_os`) REFERENCES `ordens_servico`(`id_os`) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (`id_produto`) REFERENCES `produtos`(`id_produto`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Modelos de Impressoras
CREATE TABLE IF NOT EXISTS `impressoras_modelos` (
    `id_modelo` INT AUTO_INCREMENT PRIMARY KEY,
    `nome_modelo` VARCHAR(255) NOT NULL UNIQUE,
    `fabricante` VARCHAR(100) NULL,
    `tipo_impressora` ENUM('Laser Mono', 'Laser Color', 'Jato de Tinta', 'Matricial') NULL,
    `velocidade_ppm_mono` INT NULL COMMENT 'Páginas por minuto - Monocromático',
    `velocidade_ppm_color` INT NULL COMMENT 'Páginas por minuto - Colorido',
    `ciclo_mensal_recomendado` INT NULL,
    `preco_custo` DECIMAL(10, 2) NULL COMMENT 'Custo de aquisição do modelo',
    `observacoes` TEXT NULL,
    `ativo` BOOLEAN NOT NULL DEFAULT TRUE,
    `data_cadastro` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Impressoras (Unidades Físicas)
-- Adicionado contador_atual_mono e contador_atual_color para facilitar
CREATE TABLE IF NOT EXISTS `impressoras` (
    `id_impressora` INT AUTO_INCREMENT PRIMARY KEY,
    `id_modelo` INT NOT NULL,
    `numero_serie` VARCHAR(100) NOT NULL UNIQUE,
    `patrimonio` VARCHAR(100) NULL COMMENT 'Número de patrimônio interno',
    `status_impressora` ENUM('Disponível', 'Locada', 'Manutenção', 'Desativada') NOT NULL DEFAULT 'Disponível',
    `localizacao_atual` VARCHAR(255) NULL COMMENT 'Onde a impressora se encontra fisicamente (ex: Estoque, Cliente X)',
    `data_aquisicao` DATE NULL,
    `contador_inicial_mono` INT NOT NULL DEFAULT 0 COMMENT 'Contador P&B no momento do cadastro',
    `contador_inicial_color` INT NOT NULL DEFAULT 0 COMMENT 'Contador Color no momento do cadastro',
    `contador_atual_mono` INT NOT NULL DEFAULT 0 COMMENT 'Contador atual de páginas P&B',
    `contador_atual_color` INT NOT NULL DEFAULT 0 COMMENT 'Contador atual de páginas coloridas',
    `observacoes` TEXT NULL,
    `data_cadastro` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `data_atualizacao` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`id_modelo`) REFERENCES `impressoras_modelos`(`id_modelo`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `locacoes` (
    `id_locacao` INT AUTO_INCREMENT PRIMARY KEY,
    `id_cliente` INT NOT NULL,
    `id_impressora` INT NOT NULL,
    `numero_contrato_locacao` VARCHAR(100) UNIQUE NULL COMMENT 'Número de identificação do contrato de locação, se houver',
    `data_inicio_locacao` DATETIME NOT NULL COMMENT 'Data e hora de início da locação',
    `data_fim_prevista` DATETIME NULL COMMENT 'Data e hora prevista para o fim da locação (pode ser nula para contratos indeterminados)',
    `data_fim_real` DATETIME NULL COMMENT 'Data e hora real de encerramento da locação',
    `status_locacao` ENUM('Ativa', 'Suspensa', 'Encerrada', 'Cancelada', 'Pendente de Devolução') NOT NULL DEFAULT 'Ativa',
    `valor_mensal_fixo` DECIMAL(10, 2) NOT NULL DEFAULT 0.00 COMMENT 'Valor fixo mensal da locação da impressora',
    `franquia_paginas_mono` INT NOT NULL DEFAULT 0 COMMENT 'Franquia mensal de páginas monocromáticas incluídas no valor fixo',
    `franquia_paginas_color` INT NOT NULL DEFAULT 0 COMMENT 'Franquia mensal de páginas coloridas incluídas no valor fixo',
    `preco_pagina_excedente_mono` DECIMAL(10, 4) NOT NULL DEFAULT 0.0000 COMMENT 'Preço por página monocromática excedente',
    `preco_pagina_excedente_color` DECIMAL(10, 4) NOT NULL DEFAULT 0.0000 COMMENT 'Preço por página colorida excedente',
    `contador_inicio_locacao_mono` INT NOT NULL COMMENT 'Leitura do contador P&B no início da locação',
    `contador_inicio_locacao_color` INT NOT NULL COMMENT 'Leitura do contador Color no início da locação',
    `observacoes_contrato` TEXT NULL COMMENT 'Observações e termos adicionais do contrato de locação',
    `id_usuario_responsavel` INT NULL COMMENT 'Usuário que registrou a locação',
    `data_cadastro` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `data_atualizacao` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (`id_cliente`) REFERENCES `clientes`(`id_cliente`) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (`id_impressora`) REFERENCES `impressoras`(`id_impressora`) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (`id_usuario_responsavel`) REFERENCES `usuarios`(`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE,
    -- Impede que a mesma impressora esteja "Ativa" em mais de uma locação.
    -- Para locações encerradas/canceladas, data_fim_real não será NULL.
    UNIQUE (`id_impressora`, `status_locacao`, `data_fim_real`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Períodos de Faturamento da Locação
-- Define os períodos de faturamento mensais para cada locação ativa.
CREATE TABLE IF NOT EXISTS `locacoes_periodos_faturamento` (
    `id_periodo` INT AUTO_INCREMENT PRIMARY KEY,
    `id_locacao` INT NOT NULL,
    `data_inicio_periodo` DATE NOT NULL COMMENT 'Data de início do período (geralmente dia 1 do mês)',
    `data_fim_periodo` DATE NOT NULL COMMENT 'Data de fim do período (geralmente último dia do mês)',
    `status_periodo` ENUM('Aberto', 'Fechado - Pendente Faturamento', 'Fechado - Faturado', 'Cancelado') NOT NULL DEFAULT 'Aberto',
    `data_fechamento_periodo` DATETIME NULL COMMENT 'Data e hora em que o período foi fechado para cálculo',
    `observacoes_periodo` TEXT NULL,
    `data_cadastro` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `data_atualizacao` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (`id_locacao`) REFERENCES `locacoes`(`id_locacao`) ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE (`id_locacao`, `data_inicio_periodo`) -- Garante que não haja períodos sobrepostos para a mesma locação
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Leituras de Contador da Locação
-- Registra as leituras dos contadores das impressoras para cada período de faturamento.
CREATE TABLE IF NOT EXISTS `locacoes_leituras` (
    `id_leitura` INT AUTO_INCREMENT PRIMARY KEY,
    `id_locacao` INT NOT NULL,
    `id_periodo` INT NOT NULL COMMENT 'Período de faturamento a que esta leitura pertence',
    `data_leitura` DATETIME NOT NULL COMMENT 'Data e hora em que a leitura foi realizada',
    `contador_leitura_mono` INT NOT NULL COMMENT 'Leitura atual do contador P&B no momento da leitura',
    `contador_leitura_color` INT NOT NULL COMMENT 'Leitura atual do contador colorido no momento da leitura',
    `observacoes_leitura` TEXT NULL,
    `id_usuario_registro` INT NULL COMMENT 'Usuário que registrou a leitura',
    `data_cadastro` DATETIME DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (`id_locacao`) REFERENCES `locacoes`(`id_locacao`) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (`id_periodo`) REFERENCES `locacoes_periodos_faturamento`(`id_periodo`) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (`id_usuario_registro`) REFERENCES `usuarios`(`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE,
    UNIQUE (`id_locacao`, `id_periodo`, `data_leitura`) -- Garante uma única leitura para uma locação num período em um dado momento
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Faturas de Locação (Detalhes da Fatura de um Período)
-- Esta tabela armazena os valores calculados para o faturamento de um período de locação,
-- antes de ser consolidada na tabela `financeiro_faturas`.
CREATE TABLE IF NOT EXISTS `locacoes_faturas` (
    `id_fatura_locacao` INT AUTO_INCREMENT PRIMARY KEY,
    `id_locacao` INT NOT NULL,
    `id_periodo` INT NOT NULL,
    `data_referencia_fatura` DATE NOT NULL COMMENT 'Mês/Ano de referência da fatura (ex: YYYY-MM-01)',
    `data_vencimento` DATE NOT NULL,
    `valor_fixo_calculado` DECIMAL(10, 2) NOT NULL DEFAULT 0.00 COMMENT 'Valor mensal fixo da locação (do contrato)',
    `paginas_mono_periodo` INT NOT NULL DEFAULT 0 COMMENT 'Total de páginas mono impressas no período',
    `paginas_color_periodo` INT NOT NULL DEFAULT 0 COMMENT 'Total de páginas coloridas impressas no período',
    `franquia_mono_aplicada` INT NOT NULL DEFAULT 0 COMMENT 'Franquia mono considerada para este período',
    `franquia_color_aplicada` INT NOT NULL DEFAULT 0 COMMENT 'Franquia colorida considerada para este período',
    `excedente_mono` INT NOT NULL DEFAULT 0 COMMENT 'Páginas mono que excederam a franquia',
    `excedente_color` INT NOT NULL DEFAULT 0 COMMENT 'Páginas coloridas que excederam a franquia',
    `valor_excedente_mono` DECIMAL(10, 2) NOT NULL DEFAULT 0.00 COMMENT 'Valor total do excedente mono (excedente * preço_excedente_mono)',
    `valor_excedente_color` DECIMAL(10, 2) NOT NULL DEFAULT 0.00 COMMENT 'Valor total do excedente color (excedente * preço_excedente_color)',
    `valor_total_locacao_periodo` DECIMAL(10, 2) NOT NULL DEFAULT 0.00 COMMENT 'Valor total calculado para esta locação neste período (fixo + excedentes)',
    `status_fatura_locacao` ENUM('Calculada', 'Aguardando Aprovação', 'Aprovada', 'Rejeitada', 'Faturada') NOT NULL DEFAULT 'Calculada',
    `observacoes_faturamento` TEXT NULL,
    `id_usuario_calculo` INT NULL COMMENT 'Usuário que realizou o cálculo',
    `data_cadastro` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `data_atualizacao` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (`id_locacao`) REFERENCES `locacoes`(`id_locacao`) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (`id_periodo`) REFERENCES `locacoes_periodos_faturamento`(`id_periodo`) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (`id_usuario_calculo`) REFERENCES `usuarios`(`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE,
    UNIQUE (`id_locacao`, `id_periodo`) -- Garante uma única fatura de locação por período por locação
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Faturas (Financeiro - Modificada)
-- Registra faturas geradas a partir de OS ou Locações.
-- Pode se referir a uma OS ou a uma Locação (tabela `locacoes_faturas`).
CREATE TABLE IF NOT EXISTS `financeiro_faturas` (
    `id_fatura` INT AUTO_INCREMENT PRIMARY KEY,
    `id_os` INT NULL,
    `id_fatura_locacao` INT NULL COMMENT 'Referência à fatura de locação específica',
    `id_cliente` INT NOT NULL,
    `data_emissao` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `data_vencimento` DATETIME NOT NULL,
    `valor_total` DECIMAL(10, 2) NOT NULL,
    `status_fatura` ENUM('Pendente', 'Paga', 'Atrasada', 'Cancelada', 'Parcialmente Paga') NOT NULL DEFAULT 'Pendente',
    `observacoes` TEXT NULL,
    `tipo_documento` ENUM('OS', 'Locação', 'Avulso') NOT NULL DEFAULT 'Avulso',
    `id_usuario_emissao` INT NULL COMMENT 'Usuário que emitiu a fatura',

    FOREIGN KEY (`id_os`) REFERENCES `ordens_servico`(`id_os`) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (`id_fatura_locacao`) REFERENCES `locacoes_faturas`(`id_fatura_locacao`) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (`id_cliente`) REFERENCES `clientes`(`id_cliente`) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (`id_usuario_emissao`) REFERENCES `usuarios`(`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE,
    -- Garante que uma fatura financeira se refira apenas a uma OS ou a uma fatura de locação.
    CONSTRAINT chk_fatura_origin CHECK (
        (`id_os` IS NOT NULL AND `id_fatura_locacao` IS NULL) OR
        (`id_os` IS NULL AND `id_fatura_locacao` IS NOT NULL) OR
        (`id_os` IS NULL AND `id_fatura_locacao` IS NULL AND `tipo_documento` = 'Avulso')
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Pagamentos (Financeiro)
CREATE TABLE IF NOT EXISTS `financeiro_pagamentos` (
    `id_pagamento` INT AUTO_INCREMENT PRIMARY KEY,
    `id_fatura` INT NOT NULL,
    `data_pagamento` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `valor_pago` DECIMAL(10, 2) NOT NULL,
    `metodo_pagamento` ENUM('Dinheiro', 'Cartão de Crédito', 'Cartão de Débito', 'Pix', 'Boleto', 'Transferência Bancária', 'Outros') NOT NULL,
    `observacoes` TEXT NULL,
    `id_usuario_registro` INT NULL COMMENT 'Usuário que registrou o pagamento',

    FOREIGN KEY (`id_fatura`) REFERENCES `financeiro_faturas`(`id_fatura`) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (`id_usuario_registro`) REFERENCES `usuarios`(`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Configurações do Sistema
CREATE TABLE IF NOT EXISTS `configuracoes` (
    `id_configuracao` INT AUTO_INCREMENT PRIMARY KEY,
    `chave` VARCHAR(100) NOT NULL UNIQUE COMMENT 'Nome da configuração (ex: nome_empresa, email_contato)',
    `valor` TEXT NULL COMMENT 'Valor da configuração',
    `descricao` VARCHAR(255) NULL COMMENT 'Descrição da configuração'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
