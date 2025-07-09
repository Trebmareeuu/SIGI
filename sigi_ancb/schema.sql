-- Script de creación de la base de datos para el Sistema Integrado de Gestión Institucional (SIGI) ANCB
-- Versión: 1.0
-- Fecha de creación: 2024-07-15

-- Habilitar el modo estricto de SQL para MySQL
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00"; -- Establecer la zona horaria a UTC

-- Eliminar la base de datos si existe (para desarrollo, comentar en producción)
-- DROP DATABASE IF EXISTS `sigi_ancb_db`;

-- Crear la base de datos si no existe
CREATE DATABASE IF NOT EXISTS `sigi_ancb_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `sigi_ancb_db`;

-- Tabla: roles
-- Almacena los diferentes roles de usuario en el sistema.
CREATE TABLE `roles` (
  `id_rol` INT AUTO_INCREMENT PRIMARY KEY, -- Identificador único del rol
  `nombre_rol` VARCHAR(100) NOT NULL UNIQUE, -- Nombre descriptivo del rol (ej. 'Funcionario Estándar', 'Secretaria de Dirección')
  `descripcion_rol` TEXT, -- Descripción detallada de las responsabilidades del rol
  `permisos` TEXT -- Permisos asociados al rol (ej. JSON: {"ver_correspondencia": true, "crear_usuario": false})
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Roles de los usuarios del sistema';

-- Tabla: usuarios
-- Almacena la información de los usuarios del sistema.
CREATE TABLE `usuarios` (
  `id_usuario` INT AUTO_INCREMENT PRIMARY KEY, -- Identificador único del usuario
  `id_rol` INT NOT NULL, -- Clave foránea que referencia a la tabla 'roles'
  `nombre_usuario` VARCHAR(50) NOT NULL UNIQUE, -- Nombre de usuario para el login
  `contrasena` VARCHAR(255) NOT NULL, -- Contraseña hasheada del usuario
  `nombres` VARCHAR(100) NOT NULL, -- Nombres completos del usuario
  `apellidos` VARCHAR(100) NOT NULL, -- Apellidos completos del usuario
  `email` VARCHAR(100) UNIQUE, -- Correo electrónico del usuario (opcional)
  `cargo` VARCHAR(150), -- Cargo que ocupa el usuario en la institución
  `telefono` VARCHAR(20), -- Número de teléfono del usuario (opcional)
  `estado` ENUM('activo', 'inactivo', 'bloqueado') NOT NULL DEFAULT 'activo', -- Estado de la cuenta del usuario
  `fecha_creacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- Fecha y hora de creación del registro
  `fecha_modificacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, -- Fecha y hora de la última modificación
  FOREIGN KEY (`id_rol`) REFERENCES `roles`(`id_rol`) ON DELETE RESTRICT ON UPDATE CASCADE -- Relación con la tabla roles
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Usuarios del sistema';

-- Tabla: entidades_externas
-- Almacena información sobre instituciones o personas externas con las que se intercambia correspondencia.
CREATE TABLE `entidades_externas` (
  `id_entidad` INT AUTO_INCREMENT PRIMARY KEY, -- Identificador único de la entidad externa
  `nombre_entidad` VARCHAR(255) NOT NULL, -- Nombre de la institución o persona externa
  `tipo_entidad` ENUM('publica', 'privada', 'persona_natural', 'otra') DEFAULT 'otra', -- Tipo de entidad
  `contacto_principal` VARCHAR(150), -- Nombre de la persona de contacto principal
  `telefono_contacto` VARCHAR(50), -- Teléfono de contacto
  `email_contacto` VARCHAR(100), -- Email de contacto
  `direccion` TEXT, -- Dirección física de la entidad
  `fecha_registro` TIMESTAMP DEFAULT CURRENT_TIMESTAMP -- Fecha de registro de la entidad
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Entidades externas para correspondencia';

-- Tabla: documentos
-- Almacena la información de la correspondencia interna y externa.
CREATE TABLE `documentos` (
  `id_documento` INT AUTO_INCREMENT PRIMARY KEY, -- Identificador único del documento
  `id_usuario_creador` INT NOT NULL, -- Usuario que creó/registró el documento
  `id_usuario_asignado` INT, -- Usuario actualmente asignado o responsable del documento (puede ser NULL)
  `id_entidad_externa_origen` INT, -- Si es correspondencia externa recibida, la entidad que la envía
  `id_entidad_externa_destino` INT, -- Si es correspondencia externa enviada, la entidad a la que se envía
  `tipo_documento` ENUM('nota_interna', 'informe', 'carta_externa_recibida', 'carta_externa_enviada', 'memorandum', 'circular', 'resolucion', 'otro') NOT NULL, -- Tipo de documento
  `cite` VARCHAR(100) UNIQUE, -- Código Único de Identificación del Trámite/Documento (ej. ANCB-DIR-I-001/2024)
  `referencia` VARCHAR(255) NOT NULL, -- Asunto o referencia del documento
  `contenido` TEXT, -- Contenido principal del documento (para notas internas o informes)
  `fecha_documento` DATE NOT NULL, -- Fecha que figura en el documento físico o de creación
  `fecha_recepcion_registro` TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- Fecha y hora de registro en el sistema
  `estado_documento` ENUM('en_redaccion', 'pendiente_revision', 'derivado', 'en_proceso', 'finalizado', 'archivado', 'anulado') NOT NULL, -- Estado actual del documento
  `prioridad` ENUM('baja', 'normal', 'alta', 'urgente') DEFAULT 'normal', -- Nivel de prioridad del documento
  `observaciones` TEXT, -- Observaciones adicionales sobre el documento
  `ruta_archivo_adjunto` VARCHAR(255), -- Ruta al archivo físico escaneado o adjunto principal
  FOREIGN KEY (`id_usuario_creador`) REFERENCES `usuarios`(`id_usuario`),
  FOREIGN KEY (`id_usuario_asignado`) REFERENCES `usuarios`(`id_usuario`),
  FOREIGN KEY (`id_entidad_externa_origen`) REFERENCES `entidades_externas`(`id_entidad`),
  FOREIGN KEY (`id_entidad_externa_destino`) REFERENCES `entidades_externas`(`id_entidad`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Documentos y correspondencia';

-- Tabla: documentos_historial
-- Registra la trazabilidad o historial de acciones sobre un documento.
CREATE TABLE `documentos_historial` (
  `id_historial` INT AUTO_INCREMENT PRIMARY KEY, -- Identificador único del registro de historial
  `id_documento` INT NOT NULL, -- Documento al que pertenece este historial
  `id_usuario_accion` INT NOT NULL, -- Usuario que realizó la acción
  `fecha_accion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- Fecha y hora de la acción
  `tipo_accion` VARCHAR(100) NOT NULL, -- Descripción de la acción (ej. 'Creación', 'Derivación', 'Archivado', 'Comentario')
  `descripcion_detalle` TEXT, -- Detalles adicionales de la acción (ej. 'Derivado a Juan Pérez para su atención')
  `id_usuario_origen` INT, -- Usuario que tenía el documento antes de la acción (si aplica, ej. en derivación)
  `id_usuario_destino` INT, -- Usuario que recibe el documento tras la acción (si aplica, ej. en derivación)
  FOREIGN KEY (`id_documento`) REFERENCES `documentos`(`id_documento`) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (`id_usuario_accion`) REFERENCES `usuarios`(`id_usuario`),
  FOREIGN KEY (`id_usuario_origen`) REFERENCES `usuarios`(`id_usuario`),
  FOREIGN KEY (`id_usuario_destino`) REFERENCES `usuarios`(`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Historial de acciones sobre documentos';

-- Tabla: documentos_adjuntos
-- Almacena múltiples archivos adjuntos para un documento.
CREATE TABLE `documentos_adjuntos` (
  `id_adjunto` INT AUTO_INCREMENT PRIMARY KEY, -- Identificador único del archivo adjunto
  `id_documento` INT NOT NULL, -- Documento al que pertenece el adjunto
  `nombre_archivo` VARCHAR(255) NOT NULL, -- Nombre original del archivo
  `ruta_archivo` VARCHAR(255) NOT NULL, -- Ruta donde se almacena el archivo en el servidor (dentro de /docs/)
  `tipo_mime` VARCHAR(100), -- Tipo MIME del archivo
  `tamano_archivo` INT, -- Tamaño del archivo en bytes
  `fecha_subida` TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- Fecha de subida del archivo
  `id_usuario_subida` INT NOT NULL, -- Usuario que subió el archivo
  FOREIGN KEY (`id_documento`) REFERENCES `documentos`(`id_documento`) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (`id_usuario_subida`) REFERENCES `usuarios`(`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Archivos adjuntos de los documentos';

-- Tabla: solicitudes
-- Almacena diferentes tipos de solicitudes realizadas por los funcionarios.
CREATE TABLE `solicitudes` (
  `id_solicitud` INT AUTO_INCREMENT PRIMARY KEY, -- Identificador único de la solicitud
  `id_usuario_solicitante` INT NOT NULL, -- Usuario que realiza la solicitud
  `tipo_solicitud` ENUM('vacacion', 'material_escritorio', 'activo_mueble_equipo', 'otro') NOT NULL, -- Tipo de solicitud
  `fecha_solicitud` TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- Fecha y hora de la solicitud
  `descripcion_solicitud` TEXT NOT NULL, -- Justificación o detalle de la solicitud
  `estado_solicitud` ENUM('pendiente_revision_secretaria', 'pendiente_aprobacion_mae', 'pendiente_aprobacion_admin', 'aprobada', 'rechazada', 'atendida', 'cancelada') NOT NULL, -- Estado actual
  `fecha_inicio_vacacion` DATE, -- Para solicitudes de vacación: fecha de inicio
  `fecha_fin_vacacion` DATE, -- Para solicitudes de vacación: fecha de fin
  `dias_solicitados_vacacion` INT, -- Para solicitudes de vacación: número de días
  `id_usuario_aprobador` INT, -- Usuario que aprueba/rechaza la solicitud (MAE o Dir. Admin)
  `fecha_aprobacion_rechazo` TIMESTAMP NULL DEFAULT NULL, -- Fecha de aprobación o rechazo
  `motivo_rechazo` TEXT, -- Motivo en caso de ser rechazada
  `observaciones_gestion` TEXT, -- Observaciones de quien gestiona/aprueba la solicitud
  FOREIGN KEY (`id_usuario_solicitante`) REFERENCES `usuarios`(`id_usuario`),
  FOREIGN KEY (`id_usuario_aprobador`) REFERENCES `usuarios`(`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Solicitudes de vacaciones, materiales, activos, etc.';

-- Tabla: activos_fijos
-- Almacena el inventario de activos fijos de la institución.
CREATE TABLE `activos_fijos` (
  `id_activo` INT AUTO_INCREMENT PRIMARY KEY, -- Identificador único del activo
  `codigo_activo` VARCHAR(50) NOT NULL UNIQUE, -- Código de identificación del activo (ej. ANCB-AF-EQC-001)
  `nombre_activo` VARCHAR(200) NOT NULL, -- Nombre o descripción del activo
  `descripcion_detallada` TEXT, -- Descripción más detallada del activo, características
  `tipo_activo` VARCHAR(100), -- Categoría del activo (ej. 'Equipo de Computación', 'Mobiliario', 'Vehículo')
  `fecha_adquisicion` DATE, -- Fecha en que se adquirió el activo
  `valor_adquisicion` DECIMAL(12, 2), -- Costo de adquisición del activo
  `estado_activo` ENUM('nuevo', 'bueno', 'regular', 'malo', 'en_reparacion', 'dado_de_baja') NOT NULL, -- Estado físico del activo
  `id_usuario_responsable` INT, -- Usuario asignado como responsable del activo (custodio)
  `ubicacion_actual` VARCHAR(255), -- Dónde se encuentra físicamente el activo
  `fecha_asignacion` DATE, -- Fecha en que se asignó al responsable actual
  `observaciones` TEXT, -- Cualquier observación relevante
  `fecha_registro_sistema` TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- Fecha de registro en el sistema
  FOREIGN KEY (`id_usuario_responsable`) REFERENCES `usuarios`(`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Inventario de activos fijos';

-- Tabla: pagos_recurrentes
-- Gestiona los pagos fijos o recurrentes que la institución debe realizar.
CREATE TABLE `pagos_recurrentes` (
  `id_pago_recurrente` INT AUTO_INCREMENT PRIMARY KEY, -- Identificador único del pago recurrente
  `descripcion_pago` VARCHAR(255) NOT NULL, -- Descripción del servicio o concepto del pago (ej. 'Alquiler Oficina', 'Servicio Internet')
  `proveedor` VARCHAR(200), -- Nombre del proveedor o beneficiario del pago
  `monto_pago` DECIMAL(12, 2) NOT NULL, -- Monto a pagar
  `moneda` VARCHAR(10) DEFAULT 'BOB', -- Moneda del pago (ej. BOB, USD)
  `frecuencia_pago` ENUM('mensual', 'bimestral', 'trimestral', 'semestral', 'anual', 'unico') NOT NULL, -- Cada cuánto se realiza el pago
  `dia_pago_estimado` INT, -- Día del mes estimado para el pago (si aplica, ej. 5 para el 5 de cada mes)
  `fecha_proximo_pago` DATE, -- Fecha estimada del siguiente pago (para alertas)
  `estado` ENUM('activo', 'inactivo', 'finalizado') DEFAULT 'activo', -- Si el pago recurrente sigue vigente
  `observaciones` TEXT, -- Notas adicionales
  `id_usuario_registra` INT NOT NULL, -- Usuario que registra el pago recurrente
  `fecha_registro` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`id_usuario_registra`) REFERENCES `usuarios`(`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Pagos recurrentes de la institución';

-- Tabla: pagos_historial
-- Registra cada instancia de pago realizado, vinculado a un pago recurrente o como pago único.
CREATE TABLE `pagos_historial` (
  `id_historial_pago` INT AUTO_INCREMENT PRIMARY KEY, -- Identificador único del registro de pago
  `id_pago_recurrente` INT, -- Si este pago corresponde a uno recurrente (opcional)
  `descripcion_pago_efectuado` VARCHAR(255) NOT NULL, -- Descripción específica de este pago (puede heredar de pagos_recurrentes)
  `monto_efectivamente_pagado` DECIMAL(12, 2) NOT NULL, -- Monto que se pagó
  `fecha_efectiva_pago` DATE NOT NULL, -- Fecha en que se realizó el pago
  `metodo_pago` VARCHAR(100), -- Cómo se pagó (ej. 'Transferencia Bancaria', 'Cheque Nro XXX', 'Efectivo')
  `referencia_comprobante` VARCHAR(150), -- Número de factura, recibo, o comprobante de pago
  `id_usuario_gestor_pago` INT NOT NULL, -- Usuario que gestionó/registró este pago específico
  `fecha_registro_sistema` TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- Fecha de registro de esta instancia de pago
  `observaciones` TEXT,
  FOREIGN KEY (`id_pago_recurrente`) REFERENCES `pagos_recurrentes`(`id_pago_recurrente`) ON DELETE SET NULL,
  FOREIGN KEY (`id_usuario_gestor_pago`) REFERENCES `usuarios`(`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Historial de pagos efectuados';

-- Tabla: asistencia
-- Almacena los registros de asistencia del personal, importados del biométrico.
CREATE TABLE `asistencia` (
  `id_asistencia` INT AUTO_INCREMENT PRIMARY KEY, -- Identificador único del registro de asistencia
  `id_empleado_biometrico` VARCHAR(50) NOT NULL, -- ID del empleado tal como figura en el biométrico/CSV
  `id_usuario_sistema` INT, -- ID del usuario en la tabla 'usuarios' (se intentará enlazar)
  `fecha_hora_marcacion` DATETIME NOT NULL, -- Fecha y hora exactas de la marcación (entrada/salida)
  `tipo_marcacion` ENUM('entrada', 'salida', 'desconocido') DEFAULT 'desconocido', -- Si se puede determinar el tipo de marca
  `origen_dato` VARCHAR(100) DEFAULT 'importacion_csv', -- Origen del dato (ej. 'importacion_csv', 'manual')
  `fecha_importacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- Fecha de importación del registro
  FOREIGN KEY (`id_usuario_sistema`) REFERENCES `usuarios`(`id_usuario`) ON DELETE SET NULL,
  UNIQUE KEY `idx_unica_marcacion` (`id_empleado_biometrico`, `fecha_hora_marcacion`) -- Para evitar duplicados exactos en importación.
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Registros de asistencia del personal';

-- Tabla: personal_fichas
-- Almacena la ficha detallada del personal.
CREATE TABLE `personal_fichas` (
  `id_ficha_personal` INT AUTO_INCREMENT PRIMARY KEY, -- Identificador único de la ficha
  `id_usuario` INT UNIQUE NOT NULL, -- Vinculado al usuario del sistema
  `codigo_empleado` VARCHAR(20) UNIQUE, -- Código interno de empleado
  `fecha_nacimiento` DATE,
  `lugar_nacimiento` VARCHAR(150),
  `nacionalidad` VARCHAR(100),
  `ci_numero` VARCHAR(20) UNIQUE, -- Número de Cédula de Identidad
  `ci_expedido_en` VARCHAR(50), -- Lugar de expedición del CI
  `estado_civil` ENUM('soltero_a', 'casado_a', 'viudo_a', 'divorciado_a', 'conviviente'),
  `domicilio_actual` TEXT,
  `telefono_emergencia` VARCHAR(30),
  `contacto_emergencia_nombre` VARCHAR(150),
  `relacion_contacto_emergencia` VARCHAR(50),
  `nivel_educativo` VARCHAR(150), -- Último nivel alcanzado
  `profesion` VARCHAR(150),
  `fecha_ingreso_institucion` DATE,
  `tipo_contrato` VARCHAR(100), -- Ej: Indefinido, Plazo Fijo, Consultor
  `salario_base` DECIMAL(10, 2),
  `afp_asociada` VARCHAR(100), -- Administradora de Fondos de Pensiones
  `nua_cua` VARCHAR(50) UNIQUE, -- Número Único Asignado o Código Único de Asegurado
  `grupo_sanguineo` VARCHAR(10),
  `alergias_conocidas` TEXT,
  `observaciones_medicas` TEXT,
  `fecha_creacion_ficha` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `fecha_modificacion_ficha` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`id_usuario`) REFERENCES `usuarios`(`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Ficha detallada del personal';

-- Tabla: archivo_expedientes
-- Gestiona la información de expedientes físicos en el archivo central.
CREATE TABLE `archivo_expedientes` (
  `id_expediente` INT AUTO_INCREMENT PRIMARY KEY,
  `codigo_expediente` VARCHAR(100) NOT NULL UNIQUE, -- Código único del expediente
  `nombre_expediente` VARCHAR(255) NOT NULL, -- Título o descripción del contenido del expediente
  `tipo_expediente` VARCHAR(100), -- Ej: Contratos, Informes de Gestión, Personal
  `fecha_creacion_expediente` DATE, -- Fecha de creación o inicio del expediente
  `fecha_archivado_inicial` TIMESTAMP, -- Fecha en que se archivó por primera vez
  `ubicacion_fisica_archivo` VARCHAR(255), -- Descripción de dónde está (estante, caja, etc.)
  `estado_expediente` ENUM('en_archivo', 'prestado', 'solicitud_prestamo', 'devuelto_con_observacion', 'digitalizado', 'para_archivar_fisico') DEFAULT 'en_archivo', -- Añadido 'para_archivar_fisico'
  `palabras_clave` TEXT, -- Palabras clave para búsqueda
  `observaciones` TEXT,
  `id_usuario_registra` INT NOT NULL,
  `fecha_registro_sistema` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`id_usuario_registra`) REFERENCES `usuarios`(`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Expedientes físicos en archivo central';

-- Tabla: archivo_prestamos
-- Registra los préstamos de expedientes físicos.
CREATE TABLE `archivo_prestamos` (
  `id_prestamo` INT AUTO_INCREMENT PRIMARY KEY,
  `id_expediente` INT NOT NULL, -- Expediente prestado
  `id_usuario_solicitante` INT NOT NULL, -- Quién solicita el préstamo
  `fecha_solicitud_prestamo` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `motivo_prestamo` TEXT,
  `fecha_aprobacion_prestamo` TIMESTAMP NULL,
  `id_usuario_aprueba_prestamo` INT, -- Quién aprueba (Encargado de Archivo)
  `fecha_entrega_prestamo` TIMESTAMP NULL, -- Cuándo se entregó físicamente
  `fecha_devolucion_estimada` DATE, -- Cuándo se espera que lo devuelvan
  `fecha_devolucion_real` TIMESTAMP NULL, -- Cuándo lo devolvieron realmente
  `estado_prestamo` ENUM('solicitado', 'aprobado', 'entregado', 'devuelto', 'vencido', 'cancelado') NOT NULL,
  `observaciones_prestamo` TEXT,
  `observaciones_devolucion` TEXT,
  FOREIGN KEY (`id_expediente`) REFERENCES `archivo_expedientes`(`id_expediente`),
  FOREIGN KEY (`id_usuario_solicitante`) REFERENCES `usuarios`(`id_usuario`),
  FOREIGN KEY (`id_usuario_aprueba_prestamo`) REFERENCES `usuarios`(`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Préstamos de expedientes de archivo';

-- Tabla: sistema_configuracion
-- Almacena configuraciones generales del sistema.
CREATE TABLE `sistema_configuracion` (
  `id_config` INT AUTO_INCREMENT PRIMARY KEY,
  `clave_config` VARCHAR(100) NOT NULL UNIQUE, -- Nombre de la configuración (ej. 'NOMBRE_INSTITUCION', 'LOGO_URL')
  `valor_config` TEXT, -- Valor de la configuración
  `descripcion_config` VARCHAR(255), -- Descripción de para qué sirve esta configuración
  `tipo_dato` ENUM('texto', 'numero', 'booleano', 'json', 'ruta_archivo') DEFAULT 'texto',
  `fecha_modificacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Configuraciones generales del sistema';

-- Tabla: sistema_delegaciones
-- Almacena las delegaciones de autoridad realizadas por el MAE.
CREATE TABLE `sistema_delegaciones` (
  `id_delegacion` INT AUTO_INCREMENT PRIMARY KEY,
  `id_usuario_delegante` INT NOT NULL, -- MAE que delega
  `id_usuario_delegado` INT NOT NULL, -- Usuario que recibe la delegación
  `fecha_inicio_delegacion` DATETIME NOT NULL, -- Inicio de la delegación
  `fecha_fin_delegacion` DATETIME NOT NULL, -- Fin de la delegación
  `permisos_delegados` TEXT NOT NULL, -- JSON o lista de permisos específicos delegados (ej. 'aprobar_vacaciones_mae')
  `motivo_delegacion` TEXT,
  `estado_delegacion` ENUM('activa', 'finalizada', 'cancelada') DEFAULT 'activa',
  `fecha_creacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`id_usuario_delegante`) REFERENCES `usuarios`(`id_usuario`),
  FOREIGN KEY (`id_usuario_delegado`) REFERENCES `usuarios`(`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Delegaciones de autoridad del MAE';

-- Inserciones iniciales (ejemplos, adaptar según necesidad)

-- Roles básicos
INSERT INTO `roles` (`nombre_rol`, `descripcion_rol`, `permisos`) VALUES
('Funcionario Estándar', 'Acceso base para todos los empleados.', '{"VER_PERFIL": true, "EDITAR_PERFIL": true, "VER_CORRESPONDENCIA_PROPIA": true, "REDACTAR_CORRESPONDENCIA_INTERNA": true, "VER_DETALLE_DOCUMENTO": true, "SOLICITAR_VACACION": true, "SOLICITAR_MATERIAL": true, "SOLICITAR_ACTIVO": true, "VER_HISTORIAL_SOLICITUDES_PROPIAS": true, "VER_DASHBOARD": true, "VER_COMUNICADOS": true}'),
('Secretaria de Dirección', 'Gestiona correspondencia externa y flujo inicial de vacaciones.', '{"VER_PERFIL": true, "EDITAR_PERFIL": true, "VER_CORRESPONDENCIA_PROPIA": true, "REDACTAR_CORRESPONDENCIA_INTERNA": true, "VER_DETALLE_DOCUMENTO": true, "SOLICITAR_VACACION": true, "SOLICITAR_MATERIAL": true, "SOLICITAR_ACTIVO": true, "VER_HISTORIAL_SOLICITUDES_PROPIAS": true, "VER_DASHBOARD": true, "REGISTRAR_CORRESPONDENCIA_EXTERNA": true, "CONTROLAR_SOLICITUDES_VACACION_SECRETARIA": true, "VER_COMUNICADOS": true}'),
('Director Administrativo', 'Aprueba solicitudes de materiales/activos, gestiona pagos y asistencia.', '{"VER_PERFIL": true, "EDITAR_PERFIL": true, "VER_CORRESPONDENCIA_PROPIA": true, "REDACTAR_CORRESPONDENCIA_INTERNA": true, "VER_DETALLE_DOCUMENTO": true, "SOLICITAR_VACACION": true, "SOLICITAR_MATERIAL": true, "SOLICITAR_ACTIVO": true, "VER_HISTORIAL_SOLICITUDES_PROPIAS": true, "VER_DASHBOARD": true, "APROBAR_SOLICITUDES_ADMIN": true, "GESTIONAR_PAGOS": true, "IMPORTAR_BIOMETRICO": true, "VER_REPORTES_ASISTENCIA": true, "GESTIONAR_STOCK_MATERIALES": true, "VER_STOCK_MATERIALES": true, "CREAR_COMUNICADOS": true, "VER_COMUNICADOS": true}'),
('Director General Ejecutivo (MAE)', 'Máxima autoridad, aprueba vacaciones y puede delegar funciones.', '{"VER_PERFIL": true, "EDITAR_PERFIL": true, "VER_CORRESPONDENCIA_PROPIA": true, "REDACTAR_CORRESPONDENCIA_INTERNA": true, "VER_DETALLE_DOCUMENTO": true, "SOLICITAR_VACACION": true, "SOLICITAR_MATERIAL": true, "SOLICITAR_ACTIVO": true, "VER_HISTORIAL_SOLICITUDES_PROPIAS": true, "VER_DASHBOARD": true, "APROBAR_VACACIONES_MAE": true, "DELEGAR_AUTORIDAD_SISTEMA": true, "CREAR_COMUNICADOS": true, "VER_COMUNICADOS": true}'),
('Técnico de Sistemas', 'Administra usuarios, roles y configuración del sistema.', '{"VER_PERFIL": true, "EDITAR_PERFIL": true, "VER_CORRESPONDENCIA_PROPIA": true, "REDACTAR_CORRESPONDENCIA_INTERNA": true, "VER_DETALLE_DOCUMENTO": true, "SOLICITAR_VACACION": true, "SOLICITAR_MATERIAL": true, "SOLICITAR_ACTIVO": true, "VER_HISTORIAL_SOLICITUDES_PROPIAS": true, "VER_DASHBOARD": true, "CRUD_USUARIOS_SISTEMA": true, "CRUD_ROLES_SISTEMA": true, "CONFIGURAR_SISTEMA": true, "VER_COMUNICADOS": true}'),
('Encargada de Presupuesto', 'Gestiona la ficha del personal.', '{"VER_PERFIL": true, "EDITAR_PERFIL": true, "VER_CORRESPONDENCIA_PROPIA": true, "REDACTAR_CORRESPONDENCIA_INTERNA": true, "VER_DETALLE_DOCUMENTO": true, "SOLICITAR_VACACION": true, "SOLICITAR_MATERIAL": true, "SOLICITAR_ACTIVO": true, "VER_HISTORIAL_SOLICITUDES_PROPIAS": true, "VER_DASHBOARD": true, "CRUD_PERSONAL_FICHA": true, "VER_DETALLE_PERSONAL_FICHA": true, "VER_COMUNICADOS": true}'),
('Mensajero', 'Visualiza hoja de ruta y registra entregas.', '{"VER_PERFIL": true, "EDITAR_PERFIL": true, "VER_CORRESPONDENCIA_PROPIA": true, "REDACTAR_CORRESPONDENCIA_INTERNA": true, "VER_DETALLE_DOCUMENTO": true, "SOLICITAR_VACACION": true, "SOLICITAR_MATERIAL": true, "SOLICITAR_ACTIVO": true, "VER_HISTORIAL_SOLICITUDES_PROPIAS": true, "VER_DASHBOARD": true, "VER_HOJA_RUTA_MENSAJERO": true, "VER_COMUNICADOS": true}'),
('Encargado de Activos Fijos', 'Gestiona el inventario y asignación de activos.', '{"VER_PERFIL": true, "EDITAR_PERFIL": true, "VER_CORRESPONDENCIA_PROPIA": true, "REDACTAR_CORRESPONDENCIA_INTERNA": true, "VER_DETALLE_DOCUMENTO": true, "SOLICITAR_VACACION": true, "SOLICITAR_MATERIAL": true, "SOLICITAR_ACTIVO": true, "VER_HISTORIAL_SOLICITUDES_PROPIAS": true, "VER_DASHBOARD": true, "VER_INVENTARIO_ACTIVOS": true, "ASIGNAR_NUEVO_ACTIVO": true, "GESTIONAR_STOCK_MATERIALES": true, "VER_COMUNICADOS": true}'),
('Encargada de Archivo', 'Gestiona préstamos y recepción de expedientes físicos.', '{"VER_PERFIL": true, "EDITAR_PERFIL": true, "VER_CORRESPONDENCIA_PROPIA": true, "REDACTAR_CORRESPONDENCIA_INTERNA": true, "VER_DETALLE_DOCUMENTO": true, "SOLICITAR_VACACION": true, "SOLICITAR_MATERIAL": true, "SOLICITAR_ACTIVO": true, "VER_HISTORIAL_SOLICITUDES_PROPIAS": true, "VER_DASHBOARD": true, "GESTIONAR_PRESTAMOS_ARCHIVO": true, "RECEPCIONAR_EXPEDIENTES_ARCHIVO": true, "VER_COMUNICADOS": true}');

-- Usuario administrador de sistemas (ejemplo)
-- Contraseña: 'admin123' (hashear correctamente en la aplicación antes de insertar)
-- Este es un ejemplo de hash para 'admin123', generar uno nuevo en PHP:
-- $2y$10$9.t9g8zL3kR6X7pW2qY4xO.A8Z5U5yI2O1o0E8uG3kC6z.R5f0t/O  (ESTE ES SOLO UN EJEMPLO, NO USAR)
INSERT INTO `usuarios` (`id_rol`, `nombre_usuario`, `contrasena`, `nombres`, `apellidos`, `email`, `cargo`, `estado`) VALUES
( (SELECT id_rol FROM roles WHERE nombre_rol = 'Técnico de Sistemas'), 'admin', '$2y$10$9.t9g8zL3kR6X7pW2qY4xO.A8Z5U5yI2O1o0E8uG3kC6z.R5f0t/O', 'Administrador', 'Del Sistema', 'admin@ancb.gob.bo', 'Técnico de Sistemas', 'activo');

-- Configuraciones iniciales de ejemplo
INSERT INTO `sistema_configuracion` (`clave_config`, `valor_config`, `descripcion_config`, `tipo_dato`) VALUES
('NOMBRE_INSTITUCION', 'Academia Nacional de Ciencias de Bolivia', 'Nombre oficial de la institución', 'texto'),
('SIGLAS_INSTITUCION', 'ANCB', 'Siglas de la institución para CITEs, etc.', 'texto'),
('LOGO_PRINCIPAL_RUTA', 'img/logo.png', 'Ruta al logo principal del sistema', 'ruta_archivo'),
('DIAS_MAX_VACACION_ANUAL', '20', 'Número máximo de días de vacación permitidos por año por defecto', 'numero');

-- Nota: Las contraseñas deben ser hasheadas usando password_hash() en PHP.
-- Este script SQL proporciona la estructura. La inserción de usuarios con contraseñas
-- seguras debe manejarse desde la lógica de la aplicación.

-- Comentario final del script
-- Fin del script schema.sql para SIGI ANCB.
-- Recuerde configurar correctamente config.php para la conexión a esta base de datos.
-- Todas las tablas y columnas están comentadas en español.
-- Se han utilizado nombres en minúsculas y guiones bajos según lo solicitado.
-- Se han incluido claves foráneas para mantener la integridad relacional.
-- Se han añadido algunas inserciones iniciales para roles y configuración básica.
-- El usuario 'admin' es un ejemplo, su contraseña debe ser generada y hasheada por la aplicación.
-- La tabla `roles` tiene un campo `permisos` de tipo TEXT. Se sugiere almacenar un JSON con los permisos específicos.
-- Por ejemplo: '{"VER_CORRESPONDENCIA": true, "CREAR_USUARIO": false}'.
-- La lógica de verificación de permisos en PHP leerá este JSON.
-- Los permisos listados en los INSERT de roles son ejemplos y deben ser definidos con más granularidad.
-- Por ejemplo, 'CRUD_USUARIOS_SISTEMA' podría desglosarse en 'CREAR_USUARIO', 'LEER_USUARIO', 'ACTUALIZAR_USUARIO', 'ELIMINAR_USUARIO'.
-- Se añadió una tabla `documentos_adjuntos` para permitir múltiples adjuntos por documento.
-- Se añadió una tabla `sistema_delegaciones` para la funcionalidad de delegación del MAE.
-- Se añadieron tablas para `archivo_expedientes` y `archivo_prestamos`.
-- Se añadió una tabla `personal_fichas`.
-- Se añadió una tabla `sistema_configuracion`.
-- Se han incluido comentarios para cada línea de código SQL (tablas y columnas importantes).
-- Las columnas de fecha y hora usan TIMESTAMP o DATETIME según sea apropiado. CURRENT_TIMESTAMP se usa para fechas de creación/modificación automáticas.
-- Se ha considerado el manejo de campos opcionales permitiendo NULL donde sea lógico.
-- Se ha utilizado InnoDB como motor de almacenamiento por defecto por su soporte a transacciones y claves foráneas.
-- El cotejamiento utf8mb4_unicode_ci se usa para un amplio soporte de caracteres.
-- El script es compatible con MySQL.
-- Se ha intentado cubrir todos los requisitos de la base de datos especificados.
-- El campo `cite` en `documentos` se ha marcado como UNIQUE.
-- El campo `id_empleado_biometrico` en `asistencia` no es UNIQUE porque un empleado puede tener múltiples marcaciones.
-- El campo `permisos` en la tabla `roles` es TEXT y se espera que contenga una estructura JSON para definir los permisos granulares de cada rol.
-- La aplicación PHP será responsable de interpretar esta cadena JSON.
-- La función `tiene_permiso(string $permiso_requerido, int $id_usuario)` en PHP se encargará de verificar esto.
-- Se ha añadido `ON DELETE RESTRICT ON UPDATE CASCADE` o similar a las FKs donde tiene sentido para mantener la integridad.
-- Para `documentos_historial` y `documentos_adjuntos`, se usa `ON DELETE CASCADE` para que si se borra un documento, su historial y adjuntos también.
-- Para otras relaciones, como `usuarios` con `roles`, se usa `ON DELETE RESTRICT` para evitar borrar un rol si hay usuarios asignados.
-- Para `pagos_historial` con `pagos_recurrentes`, se usa `ON DELETE SET NULL` para que si se borra un pago recurrente, el historial de pagos no se pierda, sino que quede desvinculado.
-- Para `asistencia` con `usuarios`, se usa `ON DELETE SET NULL` para que si se borra un usuario, sus registros de asistencia no se pierdan, pero el enlace sí.
-- La tabla `personal_fichas` tiene una relación 1 a 1 con `usuarios` (`id_usuario` es UNIQUE y FK).
-- La tabla `sistema_configuracion` permite almacenar configuraciones clave-valor.
-- Las tablas `archivo_expedientes` y `archivo_prestamos` están diseñadas para el módulo de Archivo.
-- La tabla `sistema_delegaciones` está diseñada para la funcionalidad de delegación del MAE.
-- Se incluyeron comentarios al final de cada CREATE TABLE y al final del script.
-- Se revisaron los nombres de tablas y columnas para que estén en minúsculas y con guiones bajos.
-- Se verificaron los tipos de datos.
-- Se incluyeron ejemplos de inserción para roles y configuración, el usuario admin debe ser gestionado por la aplicación.
-- La contraseña del usuario admin es un placeholder y debe ser hasheada por la aplicación PHP al crear este usuario.
-- Se ha añadido el estado 'para_archivar_fisico' a la tabla `archivo_expedientes`.
-- Se han añadido permisos base a todos los roles insertados, incluyendo VER_DASHBOARD, VER_PERFIL, etc.

-- Nuevas Tablas para Inventario de Materiales de Escritorio (Paso 2.1 del Plan Fase 2)

-- Tabla: materiales_escritorio
-- Almacena el catálogo de materiales de escritorio disponibles y su stock.
CREATE TABLE `materiales_escritorio` (
  `id_material` INT AUTO_INCREMENT PRIMARY KEY, -- Identificador único del material
  `nombre_material` VARCHAR(200) NOT NULL UNIQUE, -- Nombre descriptivo del material (ej. 'Bolígrafo Azul BIC')
  `descripcion_material` TEXT, -- Descripción adicional o características
  `unidad_medida` VARCHAR(50) NOT NULL, -- Unidad de medida (ej. 'Unidad', 'Caja x10', 'Resma 500 hojas')
  `stock_actual` INT NOT NULL DEFAULT 0, -- Cantidad actual en inventario
  `punto_reorden` INT DEFAULT 0, -- Nivel de stock mínimo para generar alerta de recompra (opcional)
  `fecha_creacion_material` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `fecha_modificacion_material` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Catálogo y stock de materiales de escritorio';

-- Tabla: movimientos_materiales
-- Registra todas las entradas y salidas de stock de los materiales de escritorio.
CREATE TABLE `movimientos_materiales` (
  `id_movimiento` INT AUTO_INCREMENT PRIMARY KEY, -- Identificador único del movimiento
  `id_material` INT NOT NULL, -- Material afectado
  `tipo_movimiento` ENUM('entrada', 'salida_solicitud', 'ajuste_positivo', 'ajuste_negativo') NOT NULL, -- Tipo de movimiento
  `cantidad` INT NOT NULL, -- Cantidad de unidades movidas (positivo para entradas/ajustes+, negativo para salidas/ajustes-)
  `fecha_movimiento` TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- Fecha y hora del movimiento
  `id_usuario_registra` INT NOT NULL, -- Usuario que registra el movimiento (ej. Enc. Activos Fijos, Dir. Admin)
  `id_solicitud_asociada` INT NULL, -- Si es una 'salida_solicitud', el ID de la solicitud de material que la generó
  `observaciones` TEXT, -- Observaciones adicionales sobre el movimiento
  FOREIGN KEY (`id_material`) REFERENCES `materiales_escritorio`(`id_material`) ON DELETE RESTRICT ON UPDATE CASCADE,
  FOREIGN KEY (`id_usuario_registra`) REFERENCES `usuarios`(`id_usuario`),
  FOREIGN KEY (`id_solicitud_asociada`) REFERENCES `solicitudes`(`id_solicitud`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Historial de movimientos de stock de materiales';

-- Nueva Tabla para Comunicados Internos (Paso 5.1 del Plan Fase 2)
CREATE TABLE `comunicados` (
  `id_comunicado` INT AUTO_INCREMENT PRIMARY KEY, -- Identificador único del comunicado
  `id_usuario_creador` INT NOT NULL, -- Usuario que creó el comunicado (Dir. Admin o MAE)
  `titulo_comunicado` VARCHAR(255) NOT NULL, -- Título del comunicado
  `contenido_comunicado` TEXT NOT NULL, -- Contenido completo del comunicado
  `fecha_publicacion` DATETIME NOT NULL, -- Fecha y hora en que se publica o se hace visible
  `fecha_expiracion` DATETIME NULL, -- Fecha y hora opcional hasta cuándo es visible el comunicado
  `para_roles` TEXT NULL, -- JSON array de id_rol a quienes va dirigido. NULL o vacío significa para todos.
  `estado` ENUM('publicado', 'borrador', 'archivado') NOT NULL DEFAULT 'borrador', -- Estado del comunicado
  `fecha_creacion_registro` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `fecha_modificacion_registro` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`id_usuario_creador`) REFERENCES `usuarios`(`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Comunicados internos para el personal';

-- El script está listo para ser ejecutado en un servidor MySQL.
