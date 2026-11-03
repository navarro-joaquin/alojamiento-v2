# Sistema de gestión de alojamiento — Resumen del diseño

**Estado:** planificación de base de datos y primeros servicios Laravel  
**Tecnología prevista:** Laravel 13 (PHP 8.4) + MySQL 8.4 LTS  
**Tipo de negocio:** hotel/hostal con estadías por noche y múltiples sucursales  
**País de operación:** Bolivia — moneda única: boliviano (Bs, `BOB`)  
**Interfaz:** Blade + Tailwind CSS  
**Paquetes aprobados:** `spatie/laravel-permission`, `spatie/laravel-activitylog`  
**Idioma del documento:** español

> Este documento consolida las decisiones de diseño y los ejemplos de implementación discutidos hasta ahora. Los fragmentos de código son una base de trabajo y requieren revisión, migraciones completas, pruebas y validación de permisos antes de producción.
>
> **Revisión 2:** se resolvieron contradicciones internas (anticipos frente a saldo, actor `users.id` frente a `employee_id`, estado de ocupación duplicado, descuentos, estados de excepciones), se añadieron huecos del dominio hotelero (política tarifaria, cambio de habitación, fecha de negocio, facturación, registro de huéspedes, arqueos parciales) y garantías técnicas (restricciones únicas en base de datos, orden de bloqueo canónico, idempotencia en origen). Las decisiones se resolvieron en la revisión 3.
>
> **Revisión 3:** se incorporaron las decisiones del cuestionario (sección 12): Bolivia, una sola moneda, cobro noche a noche con anticipo, devolución obligatoria del saldo a favor, sin cuentas compartidas en la fase 1, late check-out manual, `DECIMAL` + BcMath, Blade + Tailwind, paquetes de Spatie, umbrales por sucursal, conservación indefinida de datos y facturación externa.

## 1. Objetivos y alcance

El sistema debe gestionar:

- Múltiples sucursales.
- Empleados asignados a una sucursal y usuarios con permisos diferenciados por sucursal.
- Habitaciones, huéspedes, ingresos directos, cambios de habitación y salidas.
- Estadías, tarifas, cargos adicionales, descuentos y saldos pendientes.
- Cobros flexibles: al ingresar (anticipo), durante la estadía o al salir.
- Caja física compartida por varios empleados, con trazabilidad por operador.
- Sesiones de caja, arqueos parciales, cierres y diferencias.
- Devoluciones y gastos.
- Consumos de productos (minibar, tienda) vinculados al inventario.
- Inventario de consumibles y transferencias entre almacenes.
- Activos fijos asignados a sucursales o habitaciones.
- Excepciones autorizadas para permitir check-out con deuda.
- Recibos internos con numeración correlativa. La factura fiscal del SIN se emite en un sistema externo y aquí solo se registra su número de referencia.
- Auditoría de operaciones relevantes y reportes financieros/operativos.

La operación inicial será principalmente de ingreso directo, sin reserva previa. El diseño debe permitir incorporar reservas en una etapa posterior (ver fases en la sección 10).

## 2. Decisiones de arquitectura

1. **Laravel + MySQL 8.4 LTS** como base de la aplicación (se descartó MariaDB por su soporte de JSON y de índices funcionales). Desarrollo, pruebas y producción usan el mismo motor y versión. Las pruebas de concurrencia se ejecutan contra MySQL (SQLite no aplica `lockForUpdate()`).
2. **Servicios de dominio** para centralizar reglas de negocio; los controladores no deben duplicar lógica financiera.
3. **Transacciones y bloqueos** (`DB::transaction()` y `lockForUpdate()`) en operaciones concurrentes, siguiendo el **orden de bloqueo canónico** de la sección 4.
4. **Garantías en la base de datos además de los bloqueos:** restricciones únicas sobre columnas generadas para invariantes críticas (una estadía activa por habitación, una sesión abierta por caja).
5. **Montos con `DECIMAL`**, nunca `FLOAT`/`DOUBLE` para importes monetarios.
6. **Aritmética decimal segura en PHP:** `BcMath\Number` (PHP 8.4). El cast `decimal:2` de Eloquent devuelve `string`; nunca convertir a `float`. Moneda única (Bs), sin columna de moneda.
7. **Registros financieros inmutables:** no borrar pagos, devoluciones, cargos o movimientos confirmados. Corregir mediante anulaciones, devoluciones o asientos compensatorios.
8. **Idempotencia en la operación de origen** (pagos, devoluciones, gastos), no solo en el movimiento de caja.
9. **Auditoría** de solo inserción con `spatie/laravel-activitylog` para operaciones sensibles, evitando guardar contraseñas, secretos o datos personales innecesarios.
10. **Roles y permisos por sucursal** con `spatie/laravel-permission` en modo *teams*, usando `branch_id` como equipo.
11. **El actor de cada operación es siempre `users.id`** (`created_by`, `performed_by`, `processed_by`…). El empleado y la sucursal se derivan del usuario autenticado y se verifican en el servidor.
12. **La ocupación de una habitación se deriva de la estadía activa;** la habitación solo guarda su estado de limpieza y su estado operativo.
13. **Estados como PHP backed enums** (`app/Enums/`) con cast en Eloquent y columnas `string` en la base, no `ENUM` de MySQL.
14. La caja es compartida físicamente; los movimientos registran al operador, pero la diferencia de arqueo se atribuye a la sesión/caja, no automáticamente a una persona.
15. **Fechas en UTC** en la base; cada sucursal tiene su zona horaria y una **fecha de negocio** (`business_date`) que gobierna cargos nocturnos, cierres y reportes diarios.

## 3. Modelo de datos planificado

Las tablas siguientes son el modelo lógico propuesto. Las columnas indicadas son orientativas; antes de implementar se deben completar tipos, restricciones, índices, claves foráneas, `timestamps`, estados y migraciones `down()`.

Convención: toda columna de actor (`created_by`, `performed_by`, `processed_by`, `requested_by`, `approved_by`, `opened_by`, `closed_by`) referencia `users.id` con `ON DELETE RESTRICT`.

### 3.1 Sucursales, empleados y usuarios

#### `branches`
- `id`
- `code` único
- `name`
- `address`
- `phone`
- `timezone` (por defecto `America/La_Paz`)
- `check_in_time`, `check_out_time` (horario estándar de la sucursal)
- `current_business_date` (fecha de negocio vigente; avanza con la auditoría nocturna)
- `cash_difference_threshold DECIMAL(12,2)` nullable (diferencia de arqueo a partir de la cual la sesión pasa a revisión)
- `expense_approval_threshold DECIMAL(12,2)` nullable (monto a partir del cual un gasto requiere aprobación)
- `deposit_limit_nights` nullable (tope de anticipo, en noches)

Si un umbral es nulo, se usa el valor por defecto global definido en `config/` (por ejemplo, `config/lodging.php`).
- `is_active`
- `timestamps`

#### `employees`
- `id`
- `branch_id` → `branches.id` (sucursal principal)
- `employee_code`
- `first_name`, `last_name`
- `document_number`
- `phone`
- `hire_date`
- `status`
- `timestamps`

Restricciones recomendadas: `UNIQUE(branch_id, employee_code)` e índice `(branch_id, status)`.

#### `users`
- Laravel authentication (la tabla ya existe en el esqueleto).
- `employee_id` nullable y único → `employees.id` — **se agrega con una migración ALTER** posterior a `employees`.
- `is_active`
- Campos habituales de autenticación y `timestamps`.

Un usuario puede no ser un empleado operativo (por ejemplo, un administrador); las operaciones que requieren sucursal deben verificar que el usuario tenga acceso a esa sucursal.

#### Roles, permisos y acceso por sucursal

Un supervisor o administrador puede operar en varias sucursales, por lo que los roles se asignan **por sucursal**.

**Decisión:** `spatie/laravel-permission` con el modo *teams* activado (`'teams' => true`), usando `branch_id` como clave de equipo. El paquete crea sus propias tablas (`roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`). Un middleware fija el equipo activo (`setPermissionsTeamId()`) según la sucursal seleccionada por el usuario y verificada en el servidor. Los roles globales (por ejemplo, administrador general) se modelan como roles con `team_id` nulo.

Roles iniciales sugeridos: `admin`, `supervisor`, `receptionist`, `housekeeping`, `storekeeper`.

La autorización debe verificarse en el servidor mediante Policies/Gates. No confiar en campos enviados por el navegador (por ejemplo, `branch_id` o `employee_id` de un formulario).

### 3.2 Auditoría

**Decisión:** `spatie/laravel-activitylog`. El paquete crea la tabla `activity_log` (`log_name`, `description`, `subject_type`/`subject_id`, `causer_type`/`causer_id`, `event`, `attribute_changes` JSON, `properties` JSON, `timestamps`). En la versión 5 los valores anteriores y nuevos de un modelo van en `attribute_changes`, no en `properties`.

Convenciones de uso:
- `log_name` por módulo: `reception`, `cash`, `inventory`, `assets`, `security`.
- `causer` = usuario autenticado; `subject` = entidad afectada.
- `properties` incluye siempre `branch_id`, `request_id` (generado por un middleware y propagado a logs y jobs) e `ip_address`. Los cambios de atributos se registran en `attribute_changes`, y solo con los campos relevantes.
- Agregar en una migración propia una columna generada `branch_id` (`JSON_UNQUOTE(properties->"$.branch_id")`) con índice `(branch_id, created_at)`, para filtrar por sucursal.
- Las operaciones financieras se registran desde los servicios de dominio de forma explícita (`activity()->...`), no solo con el trait `LogsActivity`, para que cada operación quede con su contexto completo.

La tabla es de **solo inserción**: sin edición ni borrado desde la aplicación, y no se ejecuta la limpieza automática (`activitylog:clean`) sobre los registros financieros. Restringir el acceso a los registros y evitar datos personales completos o secretos en `properties`.

### 3.3 Habitaciones, tarifas y estadías

#### `room_types`
- `id`, `name` único, `description`, `default_capacity`

#### `rooms`
- `id`
- `branch_id` → `branches.id`
- `room_type_id` → `room_types.id`
- `room_number`
- `floor`
- `capacity`
- `housekeeping_status`: `clean`, `dirty`, `inspected`
- `operational_status`: `in_service`, `maintenance`, `out_of_service`

Restricción: `UNIQUE(branch_id, room_number)`.

La ocupación **no** se guarda en `rooms`: una habitación está ocupada si tiene una estadía `active`. Una habitación es asignable cuando no está ocupada, `operational_status = in_service` y `housekeeping_status` ∈ (`clean`, `inspected`), según la política de la sucursal.

#### `room_status_logs`
- `id`, `room_id`, `field` (`housekeeping_status` / `operational_status`), `old_value`, `new_value`, `changed_by` → `users.id`, `reason`, `occurred_at`

Historial de limpieza y mantenimiento; también permite medir tiempos de limpieza.

#### `room_rates`
- `id`
- `branch_id` → `branches.id`
- `room_type_id` → `room_types.id`
- `name`
- `amount DECIMAL(12,2)` (precio por noche)
- `occupancy` nullable (número de huéspedes al que aplica; nulo = cualquiera)
- `days_of_week` nullable (por ejemplo, tarifa de fin de semana)
- `priority` (resuelve qué tarifa gana si varias aplican)
- `valid_from`, `valid_to`
- `is_active`

El servicio de tarifas debe impedir que se solapen rangos de vigencia con la misma sucursal, tipo, ocupación, días y prioridad.

#### `guests`
- `id`
- `document_type`, `document_number`, `issuing_country`
- `document_number_hash` (índice ciego para búsqueda si `document_number` se cifra)
- `first_name`, `last_name`
- `nationality`
- `birth_date`
- `sex` nullable (si lo exige el registro de viajeros)
- `address`, `city`, `country`
- `phone`, `email`
- `timestamps`

Tipos de documento iniciales: `ci` (cédula de identidad boliviana), `passport`, `foreign_id`. Para la CI se agrega `document_complement` nullable (complemento del SEGIP), que forma parte de la clave de unicidad.

Restricción: `UNIQUE(document_type, issuing_country, document_number_hash)`. Datos personales: cifrar `document_number` con el cast `encrypted` y buscar por el hash. Se conservan **indefinidamente**, por lo que el control de acceso es la protección principal: solo los roles autorizados ven el documento completo, y en listados se muestra enmascarado. Antes de salir a producción hay que confirmar con la autoridad competente (Policía / Dirección General de Migración para huéspedes extranjeros) qué campos exige el registro de huéspedes y si se debe enviar un reporte periódico.

#### `stays`
- `id`
- `branch_id` → `branches.id`
- `room_id` → `rooms.id` (habitación vigente; el historial está en `stay_room_assignments`)
- `created_by` → `users.id`
- `check_in_at`
- `expected_check_out_at` nullable
- `check_out_at` nullable
- `status`: `active`, `completed`, `cancelled`
- `rate_id` nullable → `room_rates.id`
- `rate_amount DECIMAL(12,2)` (tarifa pactada por noche, copiada al momento del check-in)
- `adults`, `children`
- `notes`
- `active_room_id` columna generada: `IF(status = 'active', room_id, NULL)` con `UNIQUE`
- `timestamps`

La columna `active_room_id` garantiza a nivel de base que una habitación no tenga dos estadías activas, aunque un servicio omita el bloqueo. No se guarda un `total_amount` materializado: el total se calcula desde los cargos.

#### `stay_room_assignments`
- `id`
- `stay_id` → `stays.id`
- `room_id` → `rooms.id`
- `rate_amount DECIMAL(12,2)`
- `assigned_from`, `assigned_to` nullable
- `changed_by` → `users.id`
- `reason` nullable

Registra cada cambio de habitación a mitad de la estadía. El cargo nocturno usa la asignación vigente en esa fecha de negocio.

#### `stay_guests`
- `stay_id` → `stays.id`
- `guest_id` → `guests.id`
- `is_primary`
- `relationship` nullable (por ejemplo, vínculo de un menor con el adulto responsable)
- Clave primaria compuesta (`stay_id`, `guest_id`).

#### `stay_charges`
- `id`
- `stay_id` → `stays.id`
- `charge_type`: `lodging`, `late_check_out`, `product`, `service`, `discount`, `adjustment`
- `direction`: `debit` (aumenta la deuda) o `credit` (descuento/ajuste a favor)
- `description`
- `service_date` (fecha de negocio a la que corresponde el cargo)
- `quantity DECIMAL(10,2)` positiva
- `unit_price DECIMAL(12,2)` positivo
- `total_amount DECIMAL(12,2)` positivo
- `product_id` nullable → `products.id`
- `inventory_movement_id` nullable → `inventory_movements.id`
- `status`: `confirmed`, `voided`
- `created_by` → `users.id`
- `voided_by` nullable → `users.id`, `voided_at` nullable, `void_reason` nullable
- `timestamps`

Reglas:
- Los importes siempre son positivos; el signo lo da `direction`. Un descuento es un cargo `credit`.
- Anular un cargo cambia su estado a `voided` con motivo y usuario; nunca se borra ni se edita el importe. Anular o aplicar descuentos requiere permiso específico.
- Solo los cargos `confirmed` cuentan en los cálculos.
- Los importes incluyen impuestos; el desglose fiscal lo hace el sistema de facturación externo.
- El late check-out es **manual**: un operador con permiso registra un cargo `late_check_out` con el importe y el motivo. No hay cálculo automático.
- `UNIQUE(stay_id, charge_type, service_date)` aplicada a los cargos `lodging` confirmados (con una columna generada que sea nula para otros tipos o para anulados), para que la generación nocturna sea idempotente.

### 3.4 Inventario y activos fijos

#### `product_categories`
- `id`, `name` único, `description`

#### `products`
- `id`
- `category_id` nullable → `product_categories.id`
- `sku` único
- `name`, `unit`
- `sale_price DECIMAL(12,2)` nullable (si se vende a huéspedes)
- `is_sellable`, `is_active`

#### `warehouses`
- `id`
- `branch_id` → `branches.id`
- `name`, `is_active`
- Restricción `UNIQUE(branch_id, name)`.

#### `inventory_balances`
- `id`
- `warehouse_id` → `warehouses.id`
- `product_id` → `products.id`
- `quantity DECIMAL(14,3)`
- `average_cost DECIMAL(14,4)` (valoración por costo promedio ponderado)
- `UNIQUE(warehouse_id, product_id)`.

#### `inventory_movements`
- `id`
- `warehouse_id` → `warehouses.id`
- `product_id` → `products.id`
- `performed_by` → `users.id`
- `movement_type`: `entry`, `exit`, `sale`, `consumption`, `adjustment`, `transfer_in`, `transfer_out`
- `direction`: `in`, `out`
- `quantity DECIMAL(14,3)` positiva
- `unit_cost DECIMAL(14,4)`
- `balance_after DECIMAL(14,3)`
- `inventory_transfer_id` nullable → `inventory_transfers.id`
- `reference`, `notes`, `timestamps`

Cada movimiento y su actualización de saldo deben confirmarse en la misma transacción, bloqueando la fila de `inventory_balances`. Si la política lo exige, rechazar salidas que dejen saldo negativo. Una venta a un huésped crea, en la misma transacción, el `stay_charge` (`product`) y el `inventory_movement` (`sale`).

#### `inventory_transfers`
- `id`
- `from_warehouse_id`, `to_warehouse_id` → `warehouses.id`
- `status`: `draft`, `sent`, `received`, `cancelled`
- `created_by`, `received_by` nullable → `users.id`
- `sent_at`, `received_at`
- `timestamps`

#### `inventory_transfer_items`
- `id`, `inventory_transfer_id`, `product_id`, `quantity DECIMAL(14,3)`, `received_quantity DECIMAL(14,3)` nullable

Al enviar se genera `transfer_out` en el origen; al recibir, `transfer_in` en el destino. Las diferencias de recepción se registran como `adjustment` con motivo.

#### `fixed_assets`
- `id`
- `branch_id` → `branches.id`
- `room_id` nullable → `rooms.id`
- `asset_code` único
- `name`, `serial_number`
- `purchase_date`
- `purchase_cost DECIMAL(12,2)`
- `status`: `in_use`, `in_storage`, `in_repair`, `retired`

#### `asset_movements`
- `id`
- `fixed_asset_id` → `fixed_assets.id`
- `from_branch_id` nullable, `to_branch_id` nullable → `branches.id`
- `from_room_id` nullable, `to_room_id` nullable → `rooms.id`
- `performed_by` → `users.id`
- `moved_at`, `notes`

### 3.5 Caja, pagos, devoluciones y gastos

**Decisión:** las cuentas compartidas (*folios*) no entran en la fase 1. Cargos y pagos se vinculan directamente a la estadía (`stay_id` obligatorio). Las ventas a personas que no son huéspedes, los grupos o empresas con un solo pagador y los anticipos de reservas quedan para la fase 4, cuando se evaluará introducir `folios` con una migración de datos.

#### `cash_registers`
- `id`
- `branch_id` → `branches.id`
- `name`, `is_active`
- `UNIQUE(branch_id, name)`.

Representa la caja física compartida.

#### `cash_sessions`
- `id`
- `cash_register_id` → `cash_registers.id`
- `opened_by` → `users.id`
- `closed_by` nullable → `users.id`
- `reviewed_by` nullable → `users.id`, `reviewed_at` nullable
- `business_date`
- `opened_at`
- `opening_amount DECIMAL(12,2)` default 0
- `closed_at` nullable
- `expected_amount DECIMAL(12,2)` nullable
- `declared_amount DECIMAL(12,2)` nullable
- `difference_amount DECIMAL(12,2)` nullable
- `closing_notes` nullable
- `status`: `open`, `closing`, `pending_review`, `closed`
- `open_register_id` columna generada: `IF(status IN ('open','closing'), cash_register_id, NULL)` con `UNIQUE`
- `timestamps`

Índices sugeridos: `(cash_register_id, status)` y `(opened_by, opened_at)`. Para evitar dos sesiones abiertas en una caja, bloquear la fila de `cash_registers` dentro de una transacción y verificar si ya existe una sesión abierta; `open_register_id` es la garantía final a nivel de base.

Una sesión pasa a `pending_review` si `|difference_amount|` supera el umbral configurado por sucursal; un usuario con permiso de supervisor la revisa y la marca `closed`.

#### `cash_counts` (arqueos)
- `id`
- `cash_session_id` → `cash_sessions.id`
- `counted_by` → `users.id`
- `count_type`: `partial` (cambio de turno o control) o `closing`
- `expected_amount`, `declared_amount`, `difference_amount` `DECIMAL(12,2)`
- `denominations` JSON nullable (detalle de billetes y monedas)
- `notes`, `counted_at`

Permite registrar arqueos durante la sesión compartida (por ejemplo, en cada cambio de turno) sin cerrarla.

#### `payments`
- `id`
- `stay_id` → `stays.id`
- `cash_session_id` → `cash_sessions.id`
- `created_by` → `users.id` (operador responsable)
- `payment_method`: `cash`, `card`, `bank_transfer`, `qr` (pago QR interbancario, habitual en Bolivia)
- `payment_type`: `deposit` (anticipo), `settlement` (pago de saldo)
- `amount DECIMAL(12,2)` positivo, en Bs
- `external_invoice_number` nullable (número de la factura emitida en el sistema externo)
- `reference` nullable
- `operation_uuid` UUID único
- `request_hash` (huella de estadía + importe + método + tipo)
- `paid_at`
- `status`: `confirmed`, `voided`
- `timestamps`

Un pago confirmado representa un cobro positivo; no usar importes negativos para devoluciones.

#### `payment_refunds`
- `id`
- `payment_id` → `payments.id`, `RESTRICT` ante borrado
- `cash_session_id` nullable → `cash_sessions.id`, `RESTRICT`
- `processed_by` → `users.id`, `RESTRICT`
- `amount DECIMAL(12,2)` positivo
- `refund_method`
- `status` (por ejemplo, `confirmed`)
- `reference` nullable
- `reason`
- `operation_uuid` UUID único, `request_hash`
- `refunded_at`
- `timestamps`

Índices: `(payment_id, status)` y `(cash_session_id, refunded_at)`. En transacción con bloqueo del pago, verificar que las devoluciones confirmadas acumuladas no excedan el pago original. Para devolución en efectivo, exigir sesión abierta; las devoluciones por tarjeta o transferencia no reducen el efectivo físico si no se entrega efectivo.

#### `expense_categories`
- `id`, `name` único, `timestamps`

#### `expenses`
- `id`
- `branch_id` → `branches.id`
- `cash_session_id` nullable → `cash_sessions.id`
- `created_by` → `users.id`
- `approved_by` nullable → `users.id`
- `expense_category_id` → `expense_categories.id`
- `description`
- `amount DECIMAL(12,2)` positivo
- `payment_method`
- `expense_date`
- `status`: `pending_approval`, `confirmed`, `rejected`, `voided`
- `operation_uuid` UUID único, `request_hash`
- `timestamps`

Un gasto pagado en efectivo exige sesión abierta y crea su `cash_movement` en el mismo servicio y transacción. Los gastos que superen el umbral de la sucursal quedan en `pending_approval` y no afectan la caja hasta aprobarse.

#### `cash_movements`
- `id`
- `cash_session_id` → `cash_sessions.id`
- `performed_by` → `users.id`
- `movement_type`: `payment`, `refund`, `expense`, `deposit`, `withdrawal`, `adjustment`
- `payment_method` nullable
- `direction`: `in`, `out`
- `amount DECIMAL(12,2)` positivo
- `source_type`, `source_id` (referencia polimórfica, sin FK tradicional)
- `affects_cash BOOLEAN` default true
- `operation_uuid` UUID único
- `description`
- `occurred_at`
- `timestamps`

Índices: `(cash_session_id, occurred_at)` y `(source_type, source_id)`. Los movimientos confirmados son inmutables; para corregir, crear una compensación. `affects_cash` debe ser coherente con `payment_method` (solo `cash` afecta el efectivo) y lo fija el servicio, nunca la petición. Los retiros a caja fuerte (`withdrawal`) y los ajustes requieren permiso específico.

El movimiento de caja debe generarse desde un único servicio financiero, no desde múltiples controladores o listeners que puedan duplicarlo.

### 3.6 Excepciones de check-out con deuda

#### `checkout_exceptions`
- `id`
- `stay_id` → `stays.id`, `RESTRICT`
- `requested_by` → `users.id`
- `approved_by` nullable → `users.id`
- `outstanding_amount DECIMAL(12,2)`
- `reason`
- `status`: `requested`, `approved`, `rejected`, `used`, `revoked`, `expired`
- `requested_at`
- `approved_at` nullable
- `expires_at` nullable
- `used_at` nullable
- `timestamps`

Índices: `(stay_id, status)` y `(approved_by, approved_at)`. Como máximo una excepción `approved` por estadía (columna generada única o verificación bajo bloqueo).

La aprobación debe ser realizada por un usuario con permiso de supervisor y distinto del solicitante. En el check-out se recalcula el saldo bajo transacción; si no coincide con `outstanding_amount` o la excepción venció, se solicita una nueva autorización. Al usarse pasa a `used` en la misma transacción del check-out. El backend no debe confiar en un identificador de excepción enviado por el formulario sin verificarlo.

### 3.7 Recibos internos

**Decisión:** la factura fiscal del SIN se emite en un **sistema externo**. Este sistema no calcula impuestos ni se integra con el SIAT; solo emite recibos internos y guarda el número de la factura externa como referencia (`payments.external_invoice_number`).

#### `receipt_sequences`
- `id`, `branch_id`, `prefix`, `next_number`
- `UNIQUE(branch_id)`

La numeración correlativa se obtiene bloqueando la fila de la secuencia dentro de la transacción de emisión, para que no haya saltos ni duplicados.

#### `receipts`
- `id`
- `branch_id` → `branches.id`
- `stay_id` → `stays.id`
- `payment_id` nullable → `payments.id` (o `payment_refund_id` nullable para recibos de devolución)
- `number` (`UNIQUE(branch_id, number)`)
- `holder_name`, `holder_document` nullable
- `amount DECIMAL(12,2)`
- `status`: `issued`, `voided`
- `issued_by` → `users.id`, `issued_at`
- `voided_by` nullable, `voided_at` nullable, `void_reason` nullable

Un recibo emitido no se edita; se anula con motivo. Para conciliar con la contabilidad, se ofrece un reporte de pagos sin `external_invoice_number`.

### 3.8 Idempotencia

Cada operación financiera de origen (`payments`, `payment_refunds`, `expenses`) lleva un `operation_uuid` único y un `request_hash`. Ante un reintento con el mismo UUID:

- Si el hash coincide (misma operación, estadía, importe y método), se devuelve el registro existente sin crear otro.
- Si el hash no coincide, se rechaza la petición como conflicto.

El `cash_movement` hereda el `operation_uuid` de su operación de origen. Alternativa genérica: una tabla `idempotency_keys` (`key`, `user_id`, `request_hash`, `response`, `created_at`) gestionada por un middleware.

## 4. Reglas de cálculo y operación

### Orden de bloqueo canónico

Todos los servicios que bloquean más de una fila lo hacen en este orden, y omiten las que no necesitan:

1. `cash_registers`
2. `cash_sessions`
3. `stays`
4. `rooms`
5. `payments`
6. `checkout_exceptions`
7. `inventory_balances` (ordenados por `id` si son varios)
8. `receipt_sequences`

Bloquear siempre en el mismo orden evita interbloqueos (deadlocks) entre cobro, devolución, check-out y cierre.

### Saldo de una estadía

`saldo = cargos débito confirmados - cargos crédito confirmados - pagos confirmados + devoluciones confirmadas`

Un saldo negativo representa **saldo a favor** del huésped (por ejemplo, un anticipo mayor que lo consumido). **Decisión:** el saldo a favor se devuelve siempre antes de completar el check-out; no se retiene como crédito.

### Efectivo esperado en caja

`efectivo esperado = saldo inicial + entradas de efectivo - salidas de efectivo`

Solo cuentan movimientos con `affects_cash = true`. Los pagos con tarjeta, transferencia o QR pueden ser ingresos financieros, pero no aumentan el efectivo físico.

### Política tarifaria y cargos de alojamiento

**Decisión:** cobro **noche a noche con anticipo permitido**.

- La sucursal define `check_in_time` y `check_out_time`. El late check-out es **manual**: si el huésped sale después del horario, un operador con permiso registra un cargo `late_check_out` con el importe que corresponda. El sistema solo avisa en pantalla que la salida supera el horario.
- La tarifa se resuelve al hacer el check-in (sucursal, tipo de habitación, ocupación, día de la semana, vigencia y prioridad) y se copia en `stays.rate_amount`. Los cambios de tarifa posteriores no afectan las estadías en curso.
- **Auditoría nocturna:** un job programado por sucursal, que se ejecuta al cerrar la fecha de negocio, genera un cargo `lodging` por cada estadía activa con `service_date = business_date`, y luego avanza `branches.current_business_date`. Gracias a la restricción única es idempotente: un reintento no duplica noches.
- El check-in genera el cargo de la primera noche.
- **Anticipos:** se permite cobrar más que el saldo actual solo con `payment_type = deposit`, hasta un máximo de `deposit_limit_nights` de la sucursal (o el valor global por defecto) multiplicado por la tarifa pactada, descontando lo ya pagado. Un `settlement` no puede superar el saldo vigente.

### Cobro

En una transacción:
1. Bloquear sesión de caja y estadía (orden canónico).
2. Verificar que la caja esté abierta (no `closing`) y que pertenezca a la sucursal de la estadía.
3. Verificar que la estadía esté activa y que el usuario tenga permiso en esa sucursal.
4. Validar método, importe positivo y límite según `payment_type` (saldo vigente para `settlement`; tope de anticipo para `deposit`).
5. Comprobar idempotencia (`operation_uuid` + `request_hash`).
6. Crear `payments` y `cash_movements` en la misma transacción.
7. Registrar auditoría.

### Devolución

1. Bloquear la sesión de caja (si es en efectivo) y el pago original.
2. Sumar las devoluciones confirmadas existentes.
3. Rechazar si la nueva devolución excede el saldo reembolsable.
4. Validar sesión de caja abierta si se devuelve efectivo.
5. Comprobar idempotencia.
6. Crear `payment_refunds` y, cuando corresponda, `cash_movements`.
7. Registrar auditoría.

### Apertura, arqueo y cierre de caja

- La apertura bloquea `cash_registers`, comprueba que no haya otra sesión abierta y registra `business_date`.
- Un arqueo parcial (`cash_counts` de tipo `partial`) no cambia el estado de la sesión.
- El cierre bloquea la caja y la sesión, la pasa a `closing` (todo cobro, devolución o gasto rechaza sesiones que no estén `open`), calcula el efectivo esperado desde los movimientos confirmados y registra el arqueo de cierre.
- Guardar el importe declarado, el esperado, la diferencia, el usuario que cerró, la fecha y las notas. Si la diferencia supera `branches.cash_difference_threshold` (o el valor global por defecto), la sesión pasa a `pending_review`; si no, a `closed`.
- Una diferencia se atribuye a la sesión/caja compartida; no automáticamente a una persona.

### Check-in

1. Bloquear la habitación.
2. Verificar que sea asignable (no ocupada, en servicio, limpia), la sucursal y los permisos.
3. Resolver la tarifa vigente.
4. Crear la estadía, la primera `stay_room_assignments` y vincular al huésped principal y a los acompañantes.
5. Crear el cargo de la primera noche.
6. Marcar la habitación como `dirty` (`housekeeping_status`).
7. Auditar la operación.

La restricción única `active_room_id` rechaza un segundo check-in concurrente aunque falle el bloqueo.

### Cambio de habitación

1. Bloquear la estadía, la habitación de origen y la de destino (en orden de `id`).
2. Verificar que la habitación de destino sea asignable.
3. Cerrar la asignación vigente y crear una nueva, con la tarifa acordada.
4. Actualizar `stays.room_id`; marcar la habitación de origen como `dirty`.
5. Auditar la operación con el motivo.

### Check-out

1. Bloquear la estadía y la habitación.
2. Verificar que la estadía esté activa.
3. Calcular el saldo con el servicio centralizado. Si la salida supera el horario, la pantalla lo advierte para que el operador registre, si corresponde, el cargo manual de late check-out antes de continuar.
4. Si hay deuda, exigir una excepción `approved` y no vencida, aprobada por un supervisor distinto del solicitante y del operador que ejecuta el check-out.
5. Confirmar que el importe autorizado coincide con la deuda actual y marcar la excepción como `used`.
6. Si hay saldo a favor, **devolverlo obligatoriamente** en la misma transacción: crear las `payment_refunds` necesarias, empezando por los pagos más recientes, por el mismo método de cada pago (en efectivo exige sesión de caja abierta). El check-out no se completa si el saldo final no es exactamente cero o una deuda autorizada.
7. Marcar la estadía como `completed` y la habitación como `dirty`.
8. Auditar la operación.

La transición de `dirty` a `clean`/`inspected` depende del flujo de limpieza o de una acción autorizada, y se registra en `room_status_logs`.

## 5. Servicios Laravel propuestos

Ubicación: `app/Services/`. Estados en `app/Enums/`.

**Convención de nombres:** los métodos y funciones propios se nombran en español y en camelCase (`cobrarPago()`, `registrarIngreso()`). Las clases, las relaciones Eloquent, las tablas y las columnas siguen en inglés, según la convención de Laravel. Los métodos que exige el framework (`up()`, `down()`, `handle()`, `authorize()`, `rules()`, `casts()`…) conservan su nombre.

### `StayBalanceService`
Responsabilidad: calcular el saldo pendiente (o a favor) con los cargos débito/crédito, pagos y devoluciones confirmados. Usa aritmética decimal segura y los enums de estado.

### `RateService`
Responsabilidad: resolver la tarifa aplicable y validar que no haya vigencias solapadas.

### `NightAuditService`
Responsabilidad: generar los cargos nocturnos idempotentes y avanzar la fecha de negocio por sucursal. Se ejecuta mediante un comando programado (scheduler).

### `CashService`
Responsabilidad prevista:
- `abrirSesion(...)`
- `registrarArqueo(...)` (arqueo parcial)
- `cobrarPago(...)`
- `devolverPago(...)`
- `cerrarSesion(...)`
- `revisarSesion(...)`

Los cobros crean `Payment` y `CashMovement` en la misma transacción. El cierre calcula el efectivo desde los movimientos que afectan la caja física.

### `ExpenseService`
Responsabilidad: registrar, aprobar y anular gastos, creando el movimiento de caja cuando se pagan en efectivo.

### `ReceptionService`
Responsabilidad prevista:
- `registrarIngreso(...)`
- `cambiarHabitacion(...)`
- `registrarSalida(...)`

El check-in valida disponibilidad y sucursal bajo bloqueo y genera el primer cargo. El check-out con deuda requiere una excepción aprobada y de un solo uso.

### `ChargeService`
Responsabilidad: registrar cargos adicionales, descuentos y anulaciones con permiso y motivo. Las ventas de productos se coordinan con `InventoryService` en la misma transacción.

### Otros servicios
- `CheckoutExceptionService`: solicitar, aprobar, rechazar, revocar, vencer y consumir autorizaciones.
- `HousekeepingService`: transiciones de limpieza y mantenimiento de habitaciones.
- `InventoryService`: entradas, salidas, ventas, ajustes y transferencias.
- `ReceiptService`: emisión y anulación de recibos internos con numeración correlativa.
- `AuditService`: envoltorio fino sobre `spatie/laravel-activitylog` que añade siempre `branch_id`, `request_id` e `ip_address`.

La autorización usa Policies que consultan permisos de `spatie/laravel-permission` dentro del equipo (sucursal) activo.

## 6. Fragmentos de implementación ya bosquejados

En la conversación se bosquejaron ejemplos de:
- `StayBalanceService::calcular()`.
- `CashService::cobrarPago()`.
- `CashService::cerrarSesion()`.
- `ReceptionService::registrarIngreso()`.
- `ReceptionService::registrarSalida()`.
- Relaciones Eloquent `User::employee()`, `CashSession::cashRegister()`, `Stay::room()` y `Stay::guests()`.

**No se consideran listos para producción sin revisión.** Entre los puntos a corregir o verificar:
- Confirmar que todos los modelos, relaciones, columnas, estados y `$fillable` coincidan con las migraciones reales.
- Adaptar los bosquejos a las decisiones de esta revisión: actor `users.id`, ocupación derivada, cargos con `direction`, anticipos (`deposit`) y estados de excepción ampliados.
- La idempotencia debe validar `request_hash` (misma operación, estadía, importe y método); no devolver un pago basándose únicamente en el UUID y el operador.
- Seguir el orden de bloqueo canónico de la sección 4 en todos los servicios.
- Verificar la autorización por sucursal y los permisos en cada operación.
- Revisar la comparación de identificadores (`int` frente a `string`) y la configuración de casts de Eloquent.
- El cierre de caja debe impedir nuevas operaciones en todos los flujos (estado `closing`).
- El check-out debe garantizar que la excepción no pueda reutilizarse.
- Incorporar auditoría y pruebas automatizadas.
- Confirmar la disponibilidad de BCMath en el PHP que ejecuta Laravel (CLI y servidor web).

## 7. Migraciones: orden recomendado

La tabla `users` ya existe en el esqueleto de Laravel. Orden lógico sugerido:

1. `branches`
2. `employees`
3. ALTER `users`: agregar `employee_id` e `is_active`
4. Migraciones publicadas por `spatie/laravel-permission` (con `teams` activado antes de publicarlas)
5. Migraciones publicadas por `spatie/laravel-activitylog` y el índice por sucursal
6. `room_types`
7. `rooms`
8. `room_status_logs`
9. `room_rates`
10. `guests`
11. `stays`
12. `stay_room_assignments`
13. `stay_guests`
14. `product_categories`, `products`, `warehouses`, `inventory_balances`, `inventory_transfers`, `inventory_transfer_items`, `inventory_movements`
15. `stay_charges` (después de `products` e `inventory_movements`, a los que referencia)
16. `cash_registers`
17. `cash_sessions`
18. `cash_counts`
19. `payments`
20. `payment_refunds`
21. `expense_categories`
22. `expenses`
23. `cash_movements`
24. `checkout_exceptions`
25. `receipt_sequences`, `receipts`
26. `fixed_assets`, `asset_movements`

Ajustar el orden según las FK reales. En un sistema existente, crear migraciones ALTER para modificar columnas y agregar índices; no recrear tablas ya presentes.

## 8. Restricciones e índices recomendados

- `UNIQUE(branch_id, employee_code)`
- `UNIQUE(branch_id, room_number)`
- `UNIQUE(branch_id, name)` en almacenes y cajas.
- `UNIQUE(warehouse_id, product_id)` en saldos de inventario.
- `UNIQUE(operation_uuid)` en pagos, devoluciones, gastos y movimientos de caja.
- `UNIQUE(active_room_id)` en `stays` (una estadía activa por habitación).
- `UNIQUE(open_register_id)` en `cash_sessions` (una sesión abierta por caja).
- Unicidad de cargos `lodging` confirmados por `(stay_id, service_date)`.
- `UNIQUE(document_type, issuing_country, document_number_hash)` en huéspedes.
- `UNIQUE(branch_id)` en secuencias de recibos y `UNIQUE(branch_id, number)` en recibos.
- Importes y cantidades positivos donde aplique (el signo lo da `direction`).
- Saldos de inventario no negativos, si la política de negocio lo exige.
- MySQL 8.4 aplica las restricciones `CHECK` y admite índices funcionales (por ejemplo, sobre `properties->branch_id` en `activity_log`).
- Usar `ON DELETE RESTRICT` en registros financieros e históricos importantes.
- Índices para búsquedas por sucursal, estado, fechas, fecha de negocio, estadía, caja y producto.

## 9. Interfaz y reportes previstos

Pantallas funcionales a desarrollar después de consolidar los servicios:
- Panel de ocupación y estado de limpieza por sucursal.
- Check-in, identificación de huéspedes y cambio de habitación.
- Ficha de estadía con cargos, descuentos, pagos y saldo.
- Registro de cobros y consulta del historial.
- Devoluciones con autorización y motivo.
- Apertura, movimientos, arqueos parciales, cierre y revisión de caja.
- Gestión de gastos y aprobación.
- Aprobación de excepciones de deuda.
- Limpieza y mantenimiento de habitaciones.
- Inventario por almacén: entradas, salidas, ventas, ajustes y transferencias.
- Registro y movimiento de activos fijos.
- Emisión y anulación de recibos internos; registro del número de factura externa.
- Reportes de ocupación, ingresos por método de pago, saldos pendientes, gastos, cierres y diferencias, por fecha de negocio.
- Conciliación: pagos sin número de factura externa.
- Auditoría filtrable por usuario, sucursal, entidad y fecha.

Los reportes financieros deben distinguir ingresos registrados (cargos), cobros netos y efectivo físico. No sumar transferencias, tarjetas ni QR al efectivo esperado. Todos los importes se muestran en Bs.

**Stack de interfaz:** Blade + Tailwind CSS, con controladores y Form Requests de Laravel. Componentes Blade reutilizables (tablas, formularios, insignias de estado) y JavaScript mínimo solo donde haga falta interacción (por ejemplo, confirmaciones o búsqueda de huéspedes). Cada formulario financiero envía un `operation_uuid` generado al renderizarlo, para que un doble envío no duplique la operación.

## 10. Fases y próximos pasos

### Fases del MVP

- **Fase 1:** sucursales, usuarios y permisos por sucursal, habitaciones, tarifas, huéspedes, estadías, cargos (incluida la auditoría nocturna), pagos y caja.
- **Fase 2:** devoluciones (además de la devolución de saldo a favor en el check-out, que va en la fase 1), gastos, excepciones de deuda, arqueos parciales, recibos internos y reportes financieros.
- **Fase 3:** inventario, ventas de productos y activos fijos.
- **Fase 4:** reservas, disponibilidad por rango de fechas y cuentas compartidas (`folios`) para grupos, empresas y ventas a no huéspedes.

### Prioridad sugerida dentro de la fase 1

1. ✅ Configurar MySQL para desarrollo y pruebas (sección 13; `phpunit.xml` usa `alojamiento_test`).
2. ✅ Instalar `spatie/laravel-permission` 8.x (teams con `branch_id`) y `spatie/laravel-activitylog` 5.x.
3. ✅ Migraciones de la fase 1, con `up()`/`down()`, FK `RESTRICT`, restricciones `CHECK` y columnas generadas únicas: `branches`, `employees`, ALTER `users`, índice por sucursal en `activity_log`, `room_types`, `rooms`, `room_status_logs`, `room_rates`, `guests`, `stays`, `stay_room_assignments`, `stay_guests`, `stay_charges`, `cash_registers`, `cash_sessions`, `payments`, `payment_refunds`, `cash_movements`. Las garantías de base de datos están cubiertas por `tests/Feature/DatabaseConstraintsTest.php`.
   - Quedan para fases posteriores: `cash_counts`, `expenses`, `checkout_exceptions`, recibos (fase 2) y las columnas `product_id`/`inventory_movement_id` de `stay_charges` (fase 3, mediante ALTER).
4. ✅ Enums de estados en `app/Enums/` (string-backed, con `etiqueta()` en español). Algunos incluyen reglas: `PaymentMethod::afectaEfectivo()`, `CashSessionStatus::aceptaMovimientos()` y `HousekeepingStatus::permiteAsignacion()`.
5. ✅ Modelos Eloquent de la fase 1 con relaciones, casts a enums y decimales, scopes (`activas`, `confirmados`, `confirmadas`, `abiertas`) y factories con estados en español. Notas:
   - Morph map estricto en `AppServiceProvider` (`payment`, `payment_refund`, `user`…): las columnas polimórficas guardan alias, no nombres de clase.
   - `Guest` cifra `document_number` y recalcula `document_number_hash` (HMAC con `APP_KEY`) al guardar. **Si se rota `APP_KEY`, hay que recifrar los documentos y recalcular todos los hashes.**
   - Las relaciones con columnas de actor usan el sufijo `ByUser` (`createdByUser()`, `openedByUser()`…).
6. Implementar `StayBalanceService`, `RateService` y sus pruebas de cálculo.
7. Implementar `CashService::abrirSesion()`, `cobrarPago()` y `cerrarSesion()`.
8. Completar `ReceptionService` (check-in con primer cargo, cambio de habitación, check-out) y `NightAuditService`.
9. Implementar auditoría y Policies/Gates por sucursal.
10. Crear pruebas de concurrencia, idempotencia, autorización y consistencia financiera contra MySQL.
11. Recién entonces construir las pantallas y reportes.

## 11. Criterios mínimos para considerar el módulo financiero seguro

- No se puede cobrar dos veces una misma solicitud por un reintento; un reintento con datos distintos se rechaza.
- Un pago de saldo no puede superar el saldo vigente; un anticipo no puede superar el tope configurado.
- Las devoluciones nunca superan el importe reembolsable.
- Un pago con tarjeta, transferencia o QR no incrementa el efectivo físico.
- No se puede completar un check-out con saldo a favor sin devolverlo.
- No se pueden registrar movimientos nuevos en una caja en cierre o cerrada.
- No se pueden abrir dos sesiones simultáneas para la misma caja física (garantizado también por la base).
- Una excepción de deuda requiere autorización real, no puede aprobarla el solicitante, vence y se consume una sola vez.
- No se puede asignar concurrentemente una misma habitación a dos estadías (garantizado también por la base).
- La auditoría nocturna no duplica noches al reintentarse.
- Los cargos, pagos, recibos y movimientos confirmados no se borran ni se editan silenciosamente; se anulan con motivo.
- Las operaciones sensibles quedan auditadas.

## 12. Decisiones tomadas

| # | Tema | Decisión | Dónde se refleja |
|---|------|----------|------------------|
| 1 | País de operación | Bolivia | Zona horaria `America/La_Paz`, CI con complemento, método de pago QR, registro de huéspedes. |
| 2 | Moneda | Una sola: boliviano (Bs) | Sin columna `currency`. |
| 3 | Política de cobro | Noche a noche con anticipo permitido | Auditoría nocturna; `payment_type = deposit` con tope `deposit_limit_nights`. |
| 4 | Saldo a favor al salir | Devolver siempre | Check-out (sección 4): devolución obligatoria en la misma transacción. |
| 5 | Cuentas compartidas (`folios`) | No en la fase 1; se evalúan en la fase 4 | `payments.stay_id` obligatorio; sin ventas a no huéspedes. |
| 6 | Late check-out | Manual | Cargo `late_check_out` registrado por un operador con permiso. |
| 7 | Umbrales de caja y gastos | Configurables por sucursal, con valor global por defecto | Columnas en `branches` y `config/lodging.php`. |
| 8 | Representación del dinero | `DECIMAL(12,2)` + `BcMath\Number` | Migraciones, casts `decimal:2` y servicios. |
| 9 | Paquetes | `spatie/laravel-permission` (teams) y `spatie/laravel-activitylog` aprobados | Secciones 3.1 y 3.2. |
| 10 | Interfaz | Blade + Tailwind CSS | Sección 9. |
| 11 | Conservación de datos de huéspedes | Indefinida | Cifrado del documento y control de acceso estricto. |
| 12 | Facturación | Externa al sistema | Solo recibos internos y `external_invoice_number` (sección 3.7). |

### Puntos a confirmar antes de producción

- Campos exigidos y obligación de reporte del registro de huéspedes ante la Policía y la Dirección General de Migración.
- Valores iniciales de los umbrales (`cash_difference_threshold`, `expense_approval_threshold`, `deposit_limit_nights`).
- Métodos de pago efectivamente aceptados por cada sucursal.

## 13. Entorno de desarrollo local

La base de datos local corre en un contenedor Docker, separado del MariaDB que ya ocupa el puerto 3306 en el equipo.

| Dato | Valor |
|------|-------|
| Contenedor | `alojamiento-mysql` (imagen `mysql:8.4`, reinicio `unless-stopped`) |
| Puerto en el host | `3307` (→ 3306 en el contenedor) |
| Volumen persistente | `alojamiento-mysql-data` |
| Base de desarrollo | `alojamiento` |
| Base de pruebas | `alojamiento_test` |
| Usuario de la aplicación | `alojamiento` (contraseña en `.env`, no versionada) |
| Configuración del servidor | `utf8mb4` / `utf8mb4_unicode_ci`, zona horaria `+00:00` (UTC) |

Comando de creación, como referencia:

```bash
docker run -d --name alojamiento-mysql --restart unless-stopped \
  -p 3307:3306 -v alojamiento-mysql-data:/var/lib/mysql \
  -e MYSQL_ROOT_PASSWORD=<root> -e MYSQL_DATABASE=alojamiento \
  -e MYSQL_USER=alojamiento -e MYSQL_PASSWORD=<app> \
  mysql:8.4 --character-set-server=utf8mb4 \
  --collation-server=utf8mb4_unicode_ci --default-time-zone=+00:00
```

Después de crear el contenedor, la base `alojamiento_test` se crea a mano y se le dan permisos al usuario `alojamiento`.

---
**Nota de mantenimiento:** actualizar este documento cuando se acuerden las migraciones definitivas, los estados exactos, las políticas de tarifas y el flujo de inventario.
