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

**Decisión:** `spatie/laravel-permission` con el modo *teams* activado (`'teams' => true`), usando `branch_id` como clave de equipo. El paquete crea sus propias tablas (`roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`). Un middleware fija el equipo activo (`setPermissionsTeamId()`) según la sucursal seleccionada por el usuario y verificada en el servidor. Los roles se definen globalmente (`branch_id` nulo en `roles`) y se asignan por sucursal; un administrador de varias sucursales tiene el rol `admin` asignado en cada una.

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
- `folio_id` → `folios.id` (la cuenta a la que se factura el cargo; ver sección 3.5)
- `stay_id` → `stays.id` (la estadía de la que proviene el cargo)
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
- `product_category_id` nullable → `product_categories.id`
- `sku` único (en mayúsculas)
- `name`, `unit`
- `sale_price DECIMAL(12,2)` nullable (si se vende a huéspedes)
- `is_sellable`, `is_active`
- `CHECK`: el precio, si existe, es positivo, y un producto vendible tiene precio.

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
- `movement_type`: `entry`, `exit`, `sale`, `sale_return`, `consumption`, `adjustment`, `transfer_in`, `transfer_out`
- `direction`: `in`, `out`
- `quantity DECIMAL(14,3)` positiva
- `unit_cost DECIMAL(14,4)`
- `balance_after DECIMAL(14,3)`
- `inventory_transfer_id` nullable → `inventory_transfers.id`
- `reference`, `notes`
- `operation_uuid` nullable único y `request_hash` (idempotencia de los movimientos manuales)
- `timestamps`
- `CHECK`: cantidad positiva, costo y saldo no negativos, y `direction` coherente con el tipo (`entry`/`transfer_in`/`sale_return` entran, `exit`/`sale`/`consumption`/`transfer_out` salen, `adjustment` en ambos sentidos). `inventory_balances` tiene `CHECK (quantity >= 0)`.

Cada movimiento y su actualización de saldo deben confirmarse en la misma transacción, bloqueando la fila de `inventory_balances`. Si la política lo exige, rechazar salidas que dejen saldo negativo. Una venta a un huésped crea, en la misma transacción, el `stay_charge` (`product`) y el `inventory_movement` (`sale`).

#### `inventory_transfers`
- `id`
- `from_warehouse_id`, `to_warehouse_id` → `warehouses.id` (`CHECK` distintos; pueden ser de sucursales diferentes)
- `status`: `draft`, `sent`, `received`, `cancelled`
- `notes`, `reception_notes` (motivo del faltante)
- `created_by`, `sent_by`, `received_by`, `cancelled_by` nullable → `users.id`
- `sent_at`, `received_at`, `cancelled_at`
- `timestamps`

#### `inventory_transfer_items`
- `id`, `inventory_transfer_id`, `product_id` (`UNIQUE` por transferencia), `quantity DECIMAL(14,3)` positiva, `unit_cost DECIMAL(14,4)` nullable (costo promedio del origen al enviar), `received_quantity DECIMAL(14,3)` nullable (`CHECK` entre 0 y lo enviado)

Al enviar se genera `transfer_out` en el origen; al recibir, `transfer_in` en el destino. Las diferencias de recepción se registran como `adjustment` con motivo.

#### `fixed_assets`
- `id`
- `branch_id` → `branches.id`
- `room_id` nullable → `rooms.id`
- `asset_code` único (en mayúsculas)
- `name`, `serial_number` nullable
- `purchase_date` nullable
- `purchase_cost DECIMAL(12,2)` nullable, no negativo
- `status`: `in_use`, `in_storage`, `in_repair`, `retired`
- `CHECK`: solo un activo `in_use` tiene `room_id`.

#### `asset_movements`
- `id`
- `fixed_asset_id` → `fixed_assets.id`
- `from_branch_id` nullable, `to_branch_id` nullable → `branches.id`
- `from_room_id` nullable, `to_room_id` nullable → `rooms.id`
- `from_status` nullable, `to_status`
- `performed_by` → `users.id`
- `moved_at`, `notes`

El primer movimiento de cada activo es su alta (sin origen).

### 3.5 Caja, pagos, devoluciones y gastos

**Decisión (fase 4):** cargos, pagos y recibos pertenecen a una **cuenta** (*folio*), no directamente a la estadía. Cada estadía tiene su propia cuenta, que se abre en el check-in; su saldo es lo que debe el huésped. Las ventas de mostrador a personas que no se hospedan y las cuentas de grupos o empresas son cuentas sin estadía. Las fases 1 a 3 vinculaban cargos y pagos a la estadía; la migración `move_charges_payments_and_receipts_to_folios` abrió una cuenta por cada estadía existente y les pasó sus cargos, pagos y recibos.

#### `folios`
- `id`
- `branch_id` → `branches.id`
- `folio_type`: `stay` (cuenta de una estadía) o `counter` (venta de mostrador)
- `stay_id` → `stays.id` nullable, `UNIQUE`: solo y siempre en las cuentas `stay` (`CHECK folios_stay_only_for_stay_type`)
- `holder_name` nullable (titular de las cuentas sin estadía)
- `created_by` → `users.id`
- `operation_uuid` nullable `UNIQUE` y `request_hash`: idempotencia de la venta de mostrador
- `voided_by` → `users.id`, `voided_at`, `void_reason`: anulación de una venta de mostrador completa

Los cargos de una venta de mostrador no tienen estadía (`stay_charges.stay_id` nullable).

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
- `review_notes` nullable
- `status`: `open`, `closing`, `pending_review`, `closed`
- `open_register_id` columna generada: `IF(status IN ('open','closing'), cash_register_id, NULL)` con `UNIQUE`
- `timestamps`

Índices sugeridos: `(cash_register_id, status)` y `(opened_by, opened_at)`. Para evitar dos sesiones abiertas en una caja, bloquear la fila de `cash_registers` dentro de una transacción y verificar si ya existe una sesión abierta; `open_register_id` es la garantía final a nivel de base.

Una sesión pasa a `pending_review` si `|difference_amount|` supera el umbral configurado por sucursal; un usuario con permiso `cash_sessions.review`, distinto de quien la cerró, la revisa con observaciones y la marca `closed`.

#### `cash_counts` (arqueos)
- `id`
- `cash_session_id` → `cash_sessions.id`
- `counted_by` → `users.id`
- `count_type`: `partial` (cambio de turno o control) o `closing`
- `expected_amount`, `declared_amount`, `difference_amount` `DECIMAL(12,2)`
- `denominations` JSON nullable (cantidad de billetes y monedas, con el valor en centavos como clave; ver `App\Enums\CashDenomination`)
- `notes`, `counted_at`
- `CHECK (declared_amount >= 0)`; índice `(cash_session_id, counted_at)`

Permite registrar arqueos durante la sesión compartida (por ejemplo, en cada cambio de turno) sin cerrarla.

#### `payments`
- `id`
- `folio_id` → `folios.id`
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
- `id`, `name` único, `is_active`, `timestamps`

Catálogo global, compartido por todas las sucursales y a cargo del superadministrador. Las categorías se desactivan en lugar de borrarse.

#### `expenses`
- `id`
- `branch_id` → `branches.id`
- `cash_session_id` nullable → `cash_sessions.id` (la caja de la que salió el efectivo; vacía para otros métodos y para gastos pendientes)
- `expense_category_id` → `expense_categories.id`
- `created_by` → `users.id`
- `approved_by` nullable → `users.id`, `approved_at` nullable
- `resolved_by` nullable → `users.id` (quien lo rechaza o lo anula), `resolved_at` nullable, `resolution_notes` nullable
- `description`
- `amount DECIMAL(12,2)` (`CHECK > 0`)
- `payment_method`
- `reference` nullable (comprobante del proveedor)
- `expense_date` (fecha de negocio de la sucursal al registrarlo)
- `status`: `pending_approval`, `confirmed`, `rejected`, `voided`
- `operation_uuid` UUID único, `request_hash`
- `timestamps`

Índices: `(branch_id, status)`, `(branch_id, expense_date)` y `(cash_session_id, status)`. Un gasto pagado en efectivo exige sesión abierta y crea su `cash_movement` en el mismo servicio y transacción. Los gastos que superen el umbral de la sucursal quedan en `pending_approval` y no afectan la caja hasta aprobarse.

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
- `resolved_by` nullable → `users.id` (quien la rechaza, la revoca o la reemplaza)
- `outstanding_amount DECIMAL(12,2)` (`CHECK > 0`)
- `reason`
- `resolution_notes` nullable
- `status`: `requested`, `approved`, `rejected`, `used`, `revoked`, `expired`
- `requested_at`
- `approved_at` nullable
- `expires_at` nullable
- `used_at` nullable
- `resolved_at` nullable
- `open_stay_id` generada: `IF(status IN ('requested', 'approved'), stay_id, NULL)`, `UNIQUE`
- `timestamps`

Índices: `(stay_id, status)` y `(approved_by, approved_at)`. Como máximo una excepción abierta (`requested` o `approved`) por estadía, garantizado por `open_stay_id`. Una aprobación vencida conserva el estado `approved` hasta que una nueva solicitud la reemplaza y la pasa a `expired`.

La aprobación debe ser realizada por un usuario con permiso de supervisor y distinto del solicitante. En el check-out se recalcula el saldo bajo transacción; si no coincide con `outstanding_amount` o la excepción venció, se solicita una nueva autorización. Al usarse pasa a `used` en la misma transacción del check-out. El backend no debe confiar en un identificador de excepción enviado por el formulario sin verificarlo.

### 3.7 Recibos internos

**Decisión:** la factura fiscal del SIN se emite en un **sistema externo**. Este sistema no calcula impuestos ni se integra con el SIAT; solo emite recibos internos y guarda el número de la factura externa como referencia (`payments.external_invoice_number`).

#### `receipt_sequences`
- `id`, `branch_id`, `prefix`, `next_number`
- `UNIQUE(branch_id)`

La numeración correlativa se obtiene bloqueando la fila de la secuencia dentro de la transacción de emisión, para que no haya saltos ni duplicados. La fila se crea en la primera emisión de la sucursal, con su código como prefijo.

#### `receipts`
- `id`
- `branch_id` → `branches.id`
- `folio_id` → `folios.id`
- `payment_id` → `payments.id` (los recibos de devolución quedan pendientes)
- `prefix` (copiado de la secuencia) y `number` (`UNIQUE(branch_id, number)`); se imprime como `CEN-000042`
- `holder_name`, `holder_document` nullable (cifrado)
- `amount DECIMAL(12,2)`
- `status`: `issued`, `voided`
- `issued_by` → `users.id`, `issued_at`
- `voided_by` nullable, `voided_at` nullable, `void_reason` nullable
- `issued_payment_id` generada: `IF(status = 'issued', payment_id, NULL)`, `UNIQUE` (un solo recibo vigente por pago)

Un recibo emitido no se edita; se anula con motivo. Para conciliar con la contabilidad, se ofrece un reporte de pagos sin `external_invoice_number`.

### 3.8 Idempotencia

Cada operación financiera de origen (`payments`, `payment_refunds`, `expenses`) lleva un `operation_uuid` único y un `request_hash`. Ante un reintento con el mismo UUID:

- Si el hash coincide (misma operación, estadía, importe y método), se devuelve el registro existente sin crear otro.
- Si el hash no coincide, se rechaza la petición como conflicto.

El `cash_movement` hereda el `operation_uuid` de su operación de origen. Alternativa genérica: una tabla `idempotency_keys` (`key`, `user_id`, `request_hash`, `response`, `created_at`) gestionada por un middleware.

## 4. Reglas de cálculo y operación

### Orden de bloqueo canónico

Todos los servicios que bloquean más de una fila lo hacen en este orden, y omiten las que no necesitan:

0. `branches` (solo la auditoría nocturna)
1. `cash_registers`
2. `cash_sessions`
3. `stays`
4. `rooms`
5. `folios` (una cuenta de estadía se bloquea después de su estadía)
6. `payments`
7. `checkout_exceptions`
8. `expenses`
9. `inventory_transfers`
10. `inventory_balances` (si son varios, ordenados por producto)
11. `receipt_sequences`

Bloquear siempre en el mismo orden evita interbloqueos (deadlocks) entre cobro, devolución, check-out y cierre.

**Nivel de aislamiento:** la conexión MySQL usa `READ COMMITTED` (`config/database.php`). Con el `REPEATABLE READ` por defecto, una transacción que espera un bloqueo conserva la *snapshot* tomada en su primera lectura, y al obtener el bloqueo calcula el saldo con datos viejos. Así se detectó que dos cobros simultáneos desde cajas distintas podían cobrar dos veces la misma deuda. Por la misma razón, la comprobación de `operation_uuid` se repite **después** de obtener los bloqueos.

### Saldo de una cuenta

El saldo se calcula por cuenta (`FolioBalanceService`); el de una estadía es el de su propia cuenta.

`saldo = cargos débito confirmados - cargos crédito confirmados - pagos confirmados + devoluciones confirmadas`

Un saldo negativo representa **saldo a favor** del huésped (por ejemplo, un anticipo mayor que lo consumido). **Decisión:** el saldo a favor se devuelve siempre antes de completar el check-out; no se retiene como crédito.

### Efectivo esperado en caja

`efectivo esperado = saldo inicial + entradas de efectivo - salidas de efectivo`

Solo cuentan movimientos con `affects_cash = true`. Los pagos con tarjeta, transferencia o QR pueden ser ingresos financieros, pero no aumentan el efectivo físico.

### Política tarifaria y cargos de alojamiento

**Decisión:** cobro **noche a noche con anticipo permitido**.

- La sucursal define `check_in_time` y `check_out_time`. El late check-out es **manual**: si el huésped sale después del horario, un operador con permiso registra un cargo `late_check_out` con el importe que corresponda. El sistema solo avisa en pantalla que la salida supera el horario.
- La tarifa se resuelve al hacer el check-in (sucursal, tipo de habitación, ocupación, día de la semana, vigencia y prioridad) y se copia en `stays.rate_amount`. Los cambios de tarifa posteriores no afectan las estadías en curso.
- **Auditoría nocturna:** el comando `lodging:night-audit` se programa cada hora y cierra cada fecha de negocio vencida. Una fecha D vence a la hora de corte del día D+1 en la zona horaria de la sucursal (`LODGING_NIGHT_AUDIT_HOUR`, por defecto 04:00), así que un huésped que llega de madrugada pertenece a la noche anterior. Al cerrar, genera un cargo `lodging` por cada estadía activa con `service_date = D` y avanza `branches.current_business_date`. Si el servidor estuvo caído, cierra los días atrasados uno por uno. Es idempotente: las noches ya cobradas (por ejemplo, en el check-in) se omiten. Se ejecuta como el usuario `LODGING_NIGHT_AUDIT_USER_EMAIL`, que queda como autor de los cargos.
- El check-in genera el cargo de la primera noche.
- **Anticipos:** se permite cobrar más que el saldo actual solo con `payment_type = deposit`, hasta un máximo de `deposit_limit_nights` de la sucursal (o el valor global por defecto) multiplicado por la tarifa pactada, descontando lo ya pagado. Un `settlement` no puede superar el saldo vigente.

### Cobro

En una transacción:
1. Bloquear sesión de caja, estadía y cuenta (orden canónico).
2. Verificar que la caja esté abierta (no `closing`) y que pertenezca a la sucursal de la estadía.
3. Verificar que la estadía esté activa y que el usuario tenga permiso en esa sucursal.
4. Validar método, importe positivo y límite según `payment_type` (saldo vigente para `settlement`; tope de anticipo para `deposit`).
5. Comprobar idempotencia (`operation_uuid` + `request_hash`).
6. Crear `payments` y `cash_movements` en la misma transacción.
7. Registrar auditoría.

### Devolución

1. Bloquear la sesión de caja (si es en efectivo), la estadía, la cuenta y el pago original (orden canónico).
2. Sumar las devoluciones confirmadas existentes.
3. Rechazar si la nueva devolución excede el saldo reembolsable del pago o el **saldo a favor** de la estadía. Una devolución nunca crea deuda: si un cargo estaba mal, primero se anula o se descuenta y después se devuelve el saldo a favor resultante.
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

### `FolioBalanceService`
Responsabilidad: calcular el saldo pendiente (o a favor) de una cuenta con los cargos débito/crédito, pagos y devoluciones confirmados. Usa aritmética decimal segura y los enums de estado.

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
Responsabilidad: registrar, aprobar, rechazar y anular gastos (`registrar()`, `aprobar()`, `rechazar()`, `anular()`), creando el movimiento de caja cuando se pagan en efectivo.

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
- `FolioBalanceService::calcular()` (antes `StayBalanceService`).
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
- **Fase 4:** cuentas (`folios`) para ventas a no huéspedes, grupos y empresas. Las reservas y la disponibilidad por rango de fechas salieron del alcance: el negocio no toma reservas anticipadas.

### Prioridad sugerida dentro de la fase 1

1. ✅ Configurar MySQL para desarrollo y pruebas (sección 13; `phpunit.xml` usa `alojamiento_test`).
2. ✅ Instalar `spatie/laravel-permission` 8.x (teams con `branch_id`) y `spatie/laravel-activitylog` 5.x.
3. ✅ Migraciones de la fase 1, con `up()`/`down()`, FK `RESTRICT`, restricciones `CHECK` y columnas generadas únicas: `branches`, `employees`, ALTER `users`, índice por sucursal en `activity_log`, `room_types`, `rooms`, `room_status_logs`, `room_rates`, `guests`, `stays`, `stay_room_assignments`, `stay_guests`, `stay_charges`, `cash_registers`, `cash_sessions`, `payments`, `payment_refunds`, `cash_movements`. Las garantías de base de datos están cubiertas por `tests/Feature/DatabaseConstraintsTest.php`.
   - Los recibos se agregaron en la fase 2 y las columnas `product_id`/`inventory_movement_id` de `stay_charges` en la fase 3, mediante ALTER.
4. ✅ Enums de estados en `app/Enums/` (string-backed, con `etiqueta()` en español). Algunos incluyen reglas: `PaymentMethod::afectaEfectivo()`, `CashSessionStatus::aceptaMovimientos()` y `HousekeepingStatus::permiteAsignacion()`.
5. ✅ Modelos Eloquent de la fase 1 con relaciones, casts a enums y decimales, scopes (`activas`, `confirmados`, `confirmadas`, `abiertas`) y factories con estados en español. Notas:
   - Morph map estricto en `AppServiceProvider` (`payment`, `payment_refund`, `user`…): las columnas polimórficas guardan alias, no nombres de clase.
   - `Guest` cifra `document_number` y recalcula `document_number_hash` (HMAC con `APP_KEY`) al guardar. **Si se rota `APP_KEY`, hay que recifrar los documentos y recalcular todos los hashes.**
   - Las relaciones con columnas de actor usan el sufijo `ByUser` (`createdByUser()`, `openedByUser()`…).
6. ✅ `StayBalanceService::calcular()` (con `BcMath\Number`) y `RateService` (`resolverTarifa()`, `verificarSinSolapamiento()`).
7. ✅ `CashService`: `abrirSesion()`, `cobrarPago()`, `devolverPago()`, `cerrarSesion()` y `calcularEfectivoEsperado()`, más `AuditService::registrar()`. Reglas implementadas:
   - Tope de anticipo: tras un `deposit`, el saldo a favor no puede superar `deposit_limit_nights × rate_amount`, es decir, `importe ≤ saldo + noches × tarifa`.
   - Valores globales por defecto en `config/lodging.php` (provisionales, a confirmar): diferencia de caja Bs 10.00, aprobación de gastos Bs 500.00 y 7 noches de anticipo.
   - Una sesión pasa a revisión solo si la diferencia **supera** el umbral; igualarlo la cierra.
   - Los servicios no comprueban permisos: eso lo harán las Policies (paso 9).
   - Para el check-out, `ReceptionService` debe bloquear la sesión de caja **antes** que la estadía y luego llamar a `devolverPago()`, para respetar el orden de bloqueo canónico.
8. ✅ `ReceptionService` (`registrarIngreso()`, `cambiarHabitacion()`, `registrarSalida()`), `ChargeService` (`registrarNocheAlojamiento()`, `registrarCargo()`, `registrarDescuento()`, `anularCargo()`), `HousekeepingService` (`cambiarEstadoLimpieza()`, `cambiarEstadoOperativo()`), `NightAuditService` y el comando `lodging:night-audit`. Notas:
   - `registrarSalida()` solo deja salir con saldo pendiente si hay una excepción de deuda vigente por exactamente ese importe (fase 2, `CheckoutExceptionService::consumir()`).
   - El saldo a favor se devuelve en la misma transacción, empezando por el pago más reciente y por su mismo método. Si los pagos no alcanzan (por ejemplo, un descuento mayor que los cargos), la salida se rechaza.
   - `stays.rate_amount` siempre es la tarifa vigente: el cambio de habitación la actualiza, y la auditoría nocturna cobra con ella.
   - El check-in y el cambio de habitación marcan la habitación como `dirty`; vuelve a `clean` mediante `HousekeepingService`.
9. ✅ Auditoría y Policies por sucursal. Notas:
   - El middleware global `AddRequestContext` genera un `request_id` (UUID propio; nunca se acepta el que envía el cliente) y guarda `request_id` e `ip_address` en `Context`. `AuditService` los toma de ahí y la respuesta los devuelve en `X-Request-Id`. Si hay un proxy delante, configurar `TrustProxies` para que `ip_address` sea la del cliente.
   - Permisos y roles definidos en `App\Enums\BranchPermission` y `App\Enums\BranchRole` (`permisos()`); `RolesAndPermissionsSeeder` los crea como roles globales y resincroniza sus permisos en cada ejecución.
   - Recepcionista: ingresos, cambios de habitación, salidas, cargos, cobros, apertura y cierre de caja y estado de limpieza. Descuentos, anulaciones, devoluciones y estado operativo quedan para supervisor y administrador. Limpieza solo cambia el estado de limpieza; los permisos del almacenero (inventario y activos fijos) se agregaron en la fase 3.
   - Las Policies (`RoomPolicy`, `StayPolicy`, `StayChargePolicy`, `PaymentPolicy`, `CashRegisterPolicy`, `CashSessionPolicy`) usan como habilidad el nombre del método del servicio (`registrarIngreso`, `anular`…) y evalúan el permiso **en la sucursal del registro**, con `User::tienePermisoEnSucursal()`, sin depender de la sucursal activa en la sesión.
   - La salida incluye la devolución del saldo a favor, así que solo exige `stays.check_out`.
   - Un `Gate::before` deniega todo a usuarios inactivos o vinculados a un empleado inactivo.
   - No hay administrador global: el rol `admin` se asigna en cada sucursal (`User::asignarRolEnSucursal()`).
   - El middleware que fija la sucursal activa (`setPermissionsTeamId()`) se construye con las pantallas (paso 11), junto con la selección de sucursal.
10. ✅ Pruebas de concurrencia reales contra MySQL en `tests/Feature/Concurrency`. Notas:
   - `ConcurrentRace` lanza cada operación en un proceso PHP propio, con su propia conexión. La prueba bloquea antes la fila en disputa y la suelta solo cuando todas las operaciones están esperando un bloqueo. Si un servicio no bloqueara, la prueba falla con «no llegaron a esperar».
   - Estas pruebas confirman sus datos (`DatabaseTruncation`) y vacían las tablas al terminar.
   - Escenarios cubiertos: reintento simultáneo de un cobro y de una devolución (mismo `operation_uuid`), dos cobros desde cajas distintas que juntos superan el saldo, dos ingresos en la misma habitación, dos aperturas de la misma caja, salida contra cargo, cierre de caja contra cobro y dos auditorías nocturnas a la vez.
   - Bugs encontrados y corregidos: el cobro doble desde dos cajas (aislamiento, ver sección 4) y el reintento simultáneo de un cobro o una devolución, que se rechazaba por saldo en vez de devolver la operación original.
   - La autorización se cubrió en el paso 9 (`tests/Feature/Policies`).
11. ✅ Pantallas de operación. `DemoSeeder` carga datos de prueba en el entorno `local`.
   - ✅ Bloque 1: login propio (`/ingresar`, 5 intentos por minuto por correo e IP, rechaza usuarios deshabilitados), sucursal activa (`EnsureActiveBranch`: solo sucursales activas donde el usuario tiene un rol; si tiene una sola se elige sola) y panel de ocupación con cambio de estado de limpieza.
   - Usuarios de demostración (contraseña `password`): `admin@`, `supervisor@`, `recepcion@` y `limpieza@alojamiento.test`; `auditoria@alojamiento.test` ejecuta la auditoría nocturna.
   - ✅ Bloque 2: check-in (el huésped se busca por documento con `GuestService::identificar()` y, si existe, se actualizan sus datos de contacto) y ficha de estadía con cargos, descuentos, anulaciones, cobros, salida y cambio de habitación. Las `BusinessRuleException` se muestran como error del formulario (clave `operation`) y no se registran como errores de la aplicación. El formulario de cobro lleva un `operation_uuid`: enviarlo dos veces registra un solo pago.
   - ✅ Bloque 3: cajas (apertura, detalle de la sesión con efectivo esperado y neto por método, y cierre con el efectivo contado) y estado operativo de las habitaciones (sacar una habitación de servicio exige motivo). Las cajas solo las ven quienes pueden abrirlas o cerrarlas.
12. ✅ Catálogos:
   - ✅ Bloque A: superadministrador. Es una marca `users.is_super_admin` que no se puede asignar masivamente; solo se da o se quita con `php artisan lodging:super-admin {email} [--revoke]`, y queda auditada. Un `Gate::before` le concede todo, salvo que esté deshabilitado, y puede trabajar en cualquier sucursal activa. Gestiona sucursales y tipos de habitación; sus rutas no exigen sucursal activa, para poder crear la primera.
   - Permisos de administración de sucursal (`rooms.manage`, `room_rates.manage`, `cash_registers.manage`, `staff.manage`): solo los tiene el rol `admin`; el supervisor conserva solo los operativos.
   - ✅ Bloque B: habitaciones, tarifas y cajas de la sucursal, a cargo de su admin (menú "Configuración"). Siempre se crean en la sucursal activa, y las de otra sucursal responden 404. El número de habitación y el nombre de la caja son únicos por sucursal. Una tarifa activa que se solapa con otra se rechaza como error del formulario (`RateService::verificarSinSolapamiento`, dentro de la transacción), y cada alta o cambio de tarifa queda auditado. Cambiar una tarifa no altera las estadías en curso. No se puede desactivar una caja con una sesión abierta.
   - ✅ Bloque C: cuentas de usuario y personal. El superadministrador crea y edita las cuentas (nombre, correo, contraseña y si está habilitada; una cuenta deshabilitada se desconecta en su siguiente petición) y no puede deshabilitar la suya. El admin de cada sucursal (`staff.manage`) asigna roles en su sucursal a cuentas existentes, por correo: cada persona tiene un solo rol por sucursal, y asignarle otro reemplaza el actual sin tocar sus roles en otras sucursales. También puede quitar a alguien de la sucursal, pero nunca cambiar ni quitar su propio rol. Las altas, cambios de cuenta y cambios de rol quedan auditados en el registro `security`, sin la contraseña.

### Prioridad sugerida dentro de la fase 2

1. ✅ Devoluciones manuales desde la ficha de estadía (supervisor y administrador): se elige el pago, el importe y el motivo; si el pago fue en efectivo, sale de una caja abierta. Solo se devuelve saldo a favor (sección 4). El formulario lleva `refund_operation_uuid`, así que un doble envío registra una sola devolución. Hay una prueba de concurrencia: dos devoluciones de pagos distintos no superan juntas el saldo a favor.
2. ✅ Excepciones de deuda (`CheckoutExceptionService`: `solicitar()`, `aprobar()`, `rechazar()` y `consumir()`):
   - Se solicita desde la ficha de estadía (permiso `checkout_exceptions.request`, que también tienen los recepcionistas) por la deuda actual y con motivo. Una solicitud nueva reemplaza la pendiente o la autorización vencida o con otra deuda; no reemplaza una autorización vigente.
   - La autoriza otra persona con `checkout_exceptions.approve` (supervisor y administrador), desde la ficha o desde **Salidas con deuda**, que lista las pendientes y autorizadas de la sucursal con la deuda actual. Dura `lodging.checkout_exception_minutes` (120 por defecto). También se puede rechazar o revocar con motivo.
   - El check-out la consume en su transacción si sigue vigente, coincide exactamente con la deuda y no lo registra quien la autorizó (el solicitante sí puede). Si la deuda cambió, hay que solicitar otra.
   - La deuda queda en la estadía finalizada y se cobra después desde su ficha ("Cobrar deuda pendiente"): `cobrarPago()` acepta en una estadía finalizada solo pagos de saldo (`settlement`), nunca anticipos, y sin superar la deuda.
3. ✅ Gastos con categorías, salida de caja y aprobación por umbral (`ExpenseService`; pantalla **Gastos**):
   - Permisos: `expenses.register` (recepcionista, supervisor y administrador), `expenses.approve` y `expenses.void` (supervisor y administrador). Las categorías son globales y las gestiona el superadministrador (menú "Administración"); `ExpenseCategorySeeder` carga las iniciales.
   - Hasta el umbral de la sucursal (`expense_approval_threshold`, o `lodging.expense_approval_threshold`, Bs 500.00 por defecto), el gasto se confirma al registrarlo; igualar el umbral no exige aprobación. Por encima queda `pending_approval` y lo aprueba o rechaza (con motivo) otra persona: quien lo registró nunca puede aprobarlo.
   - Un gasto en efectivo sale de una caja abierta de la sucursal cuando se confirma: al registrarlo o, si estaba pendiente, al aprobarlo, desde la caja que elige quien aprueba. Una caja no entrega más efectivo del esperado (prueba de concurrencia: dos gastos que juntos lo superan no pasan los dos). Tarjeta, transferencia y QR no usan caja.
   - Anular un gasto confirmado exige motivo; si fue en efectivo, el dinero vuelve con un movimiento de entrada a la caja abierta que se elija (puede no ser la original si ya cerró).
   - El formulario lleva `operation_uuid`: un doble envío registra un solo gasto. Registro, aprobación, rechazo y anulación quedan auditados.
4. ✅ Arqueos parciales y revisión de cierres (`CashService::registrarArqueo()` y `revisarSesion()`):
   - En la sesión abierta, quien puede cerrar la caja registra arqueos parciales (por ejemplo, en el cambio de turno): guardan el efectivo esperado, el contado y la diferencia, sin cerrar la caja ni mover dinero. El cierre también deja su arqueo (`count_type = closing`).
   - El efectivo contado se puede detallar por billetes y monedas bolivianos; en ese caso el importe es su suma, y el servicio rechaza un detalle que no coincida con el importe.
   - Las sesiones en `pending_review` aparecen en **Cajas → Cierres por revisar** para quien tiene `cash_sessions.review` (supervisor y administrador). La revisión exige observaciones, la hace alguien distinto de quien cerró y deja la sesión `closed`. Arqueos y revisiones quedan auditados.
5. ✅ Recibos internos (`ReceiptService`: `emitir()`, `anular()` y `registrarFacturaExterna()`):
   - Desde la tabla de pagos de la ficha de estadía, quien cobra emite el recibo de un pago confirmado por su importe total. El titular se propone a partir del huésped principal y se puede cambiar (por ejemplo, a una empresa con su NIT). La página del recibo se imprime sin el menú.
   - Numeración correlativa por sucursal con el código como prefijo, sin saltos ni duplicados (prueba de concurrencia: dos emisiones simultáneas reciben números consecutivos). Un pago tiene como máximo un recibo vigente; para corregirlo, un supervisor o administrador (`receipts.void`) lo anula con motivo y se emite otro con número nuevo.
   - En la misma tabla se guarda el número de la factura emitida en el sistema externo (`payments.external_invoice_number`). Emisiones, anulaciones y cambios del número de factura quedan auditados.
   - **Pendiente:** recibos de devoluciones.
6. ✅ Reportes financieros y consulta de auditoría:
   - **Reportes** (`reports.view`, supervisor y administrador; `FinancialReportService`), para un rango de fechas de negocio (por defecto la actual, hasta 93 días): ocupación (noches vendidas sobre habitaciones × días), cargos y descuentos por tipo, cobros, devoluciones y gastos por método con su neto, gastos por categoría, sesiones de caja con sus diferencias y cobros sin número de factura externa. También lista los saldos pendientes: estadías activas con deuda y estadías finalizadas que salieron con deuda autorizada.
   - Cargos y gastos se asignan por su fecha de negocio. Cobros y devoluciones, que solo tienen hora, se asignan a la fecha de negocio que va desde la hora de la auditoría nocturna (`lodging.night_audit_hour`) de ese día hasta la misma hora del siguiente; así un cobro a la 01:00 cuenta en la noche que paga.
   - **Auditoría** (`audit.view`, solo el administrador de la sucursal): consulta de solo lectura del registro de actividad de la sucursal, con filtros por registro, evento y fechas, y el detalle de cada operación. Las operaciones globales (sucursales y cuentas) no tienen sucursal y no aparecen.

### Prioridad sugerida dentro de la fase 3

1. ✅ Catálogo de productos y almacenes:
   - **Productos** y **Categorías de producto** son catálogos globales que gestiona el superadministrador (menú "Administración"), para que existencias y precios sean comparables entre sucursales. El código (SKU) se guarda en mayúsculas y es único. Un producto que se vende a huéspedes necesita precio de venta (validado en el formulario y por `CHECK`).
   - **Almacenes**: cada sucursal tiene los suyos (por ejemplo, depósito central, minibar, tienda de recepción), con nombre único por sucursal. Los gestiona el admin de la sucursal (`warehouses.manage`, menú "Configuración"); los de otra sucursal responden 404.
   - Productos y almacenes no se borran: se desactivan y conservan su historial.
2. ✅ Movimientos de inventario (`InventoryService`: `registrarEntrada()`, `registrarSalida()` y `registrarAjuste()`; pantalla **Inventario**):
   - Permisos: `inventory.view` e `inventory.register` (almacenero, supervisor y administrador) e `inventory.adjust` (supervisor y administrador): quien custodia las existencias no corrige sus diferencias.
   - Cada movimiento bloquea la fila de `inventory_balances` (la crea vacía la primera vez, después de intentar bloquearla) y actualiza saldo y costo en la misma transacción. Las existencias nunca quedan negativas: una salida mayor se rechaza en el formulario, y la base lo garantiza con `CHECK` (prueba de concurrencia: dos consumos que juntos superan el saldo no pasan los dos).
   - Valoración por costo promedio ponderado: cada entrada lo recalcula con su costo unitario; salidas, consumos y ajustes salen al costo promedio vigente y no lo cambian. Con saldo cero se conserva el último costo.
   - Salidas manuales: `exit` (merma, rotura, devolución al proveedor) y `consumption` (consumo interno, como amenidades), siempre con motivo. Ventas y transferencias no se registran a mano.
   - El ajuste se hace por conteo físico: se indica la cantidad contada y el sistema registra la diferencia, calculada con el saldo bloqueado; un conteo igual al saldo se rechaza.
   - Los formularios llevan `operation_uuid`: un doble envío registra un solo movimiento. Todos los movimientos quedan auditados en el registro `inventory`. Producto o almacén inactivos no admiten movimientos.
3. ✅ Venta de productos a huéspedes (`ChargeService::venderProducto()`; tarjeta "Vender producto" de la ficha de estadía):
   - Quien puede registrar cargos (`charges.add`) elige un producto vendible y activo, el almacén de la sucursal del que sale y la cantidad. El precio es el de venta vigente del producto.
   - En una sola transacción (bloqueo `stays` → `inventory_balances`) se crea el movimiento `sale` al costo promedio y el cargo `product` enlazado (`stay_charges.product_id` e `inventory_movement_id`, único). Sin existencias suficientes no se cobra nada.
   - Los cargos `product` ya no se registran a mano: el tipo salió de `ChargeService::MANUAL_DEBIT_TYPES`, y lo que no tiene inventario se cobra como `service`. Un `CHECK` exige que un cargo enlazado a una venta sea `product` y nombre su producto; los cargos de producto manuales anteriores conservan ambas columnas vacías.
   - Anular el cargo de una venta devuelve las existencias con un movimiento `sale_return` al costo con que salieron, en la misma transacción. Si el huésped sí consumió el producto y se le perdona, corresponde un descuento, no una anulación.
4. ✅ Transferencias entre almacenes, también entre sucursales (`InventoryTransferService`: `crear()`, `enviar()`, `recibir()` y `cancelar()`; pantalla **Transferencias**):
   - Permiso `inventory.transfer` (almacenero, supervisor y administrador). La sucursal de origen crea, envía y cancela; la de destino recibe. Cada una ve las transferencias que salen de sus almacenes o llegan a ellos; las demás responden 404.
   - El borrador no mueve existencias y es lo único que se cancela. Al enviar se registra un `transfer_out` por producto al costo promedio del origen, que queda guardado en el ítem; si falta stock de un producto, no se envía nada.
   - Al recibir se registra el `transfer_in` de lo enviado, al mismo costo. Si llegó menos, la diferencia sale del destino como `adjustment` con el motivo obligatorio (`reception_notes`), así el origen conserva lo que envió y el faltante queda en el almacén que lo detectó. Todos los movimientos llevan `inventory_transfer_id`.
   - Bloqueo `inventory_transfers` → `inventory_balances` en orden de producto; el estado se verifica con la transferencia bloqueada (prueba de concurrencia: dos recepciones simultáneas registran una sola). Creación, envío, recepción y cancelación quedan auditadas.
5. ✅ Activos fijos (`FixedAssetService`: `registrar()`, `mover()` y `darDeBaja()`; pantalla **Activos fijos**):
   - Permisos: `fixed_assets.manage` (almacenero, supervisor y administrador) para registrar, editar datos y mover; `fixed_assets.retire` (supervisor y administrador) para dar de baja.
   - Cada cambio de sucursal, habitación o estado queda en `asset_movements` con origen y destino, empezando por el alta. Solo un activo en uso se ubica en una habitación, y siempre de su sucursal (validado y garantizado por `CHECK`); al pasar a depósito o reparación deja la habitación.
   - Enviar un activo a otra sucursal se lo entrega: desde entonces lo gestiona esa sucursal y deja de verse en la de origen; su historial completo se ve desde la nueva.
   - La baja exige motivo y es definitiva: un activo dado de baja no se mueve más. La edición solo cambia datos descriptivos (código, nombre, serie, compra); ubicación y estado cambian únicamente con movimientos. Alta, movimientos y bajas quedan auditados en el registro `assets`, en la sucursal donde se hicieron.

**Fase 3 completa.**

### Prioridad sugerida dentro de la fase 4

1. ✅ Cuentas (`folios`) sin cambios visibles:
   - Cada estadía abre su cuenta en el check-in. Cargos, pagos y recibos llevan `folio_id`; los cargos conservan `stay_id` como la estadía de la que provienen, y pagos y recibos dejan de tener `stay_id`.
   - `FolioBalanceService` reemplaza a `StayBalanceService`; cobro, devolución, check-out, excepciones de deuda, recibos y reportes usan el saldo de la cuenta. `CashService::cobrarPago()` recibe la cuenta y bloquea estadía → cuenta.
   - La migración abre una cuenta por cada estadía existente y le pasa sus cargos, pagos y recibos; también se puede revertir.
2. ✅ Ventas de mostrador a personas que no se hospedan (`CounterSaleService`: `vender()` y `anular()`; pantalla **Ventas de mostrador**):
   - Quien registra cargos y cobra (`charges.add` y `payments.collect`) elige el almacén de la sucursal y ve su catálogo: los productos vendibles con existencias, con precio y saldo, un buscador por nombre o código y el total calculado al escribir las cantidades. Indica la caja abierta y el método; el cliente es opcional. En una transacción (bloqueo `cash_sessions` → `folios` → `inventory_balances` por producto) se abre una cuenta `counter`, salen los productos (movimientos `sale`), se crean sus cargos y se cobra el total como `settlement`. Sin existencias suficientes no se vende ni se cobra nada.
   - El formulario lleva `operation_uuid` (guardado en la cuenta y en el pago): un doble envío devuelve la misma venta, y el mismo UUID con otros datos se rechaza.
   - Una cuenta sin estadía solo admite pagos de saldo: los anticipos son de estadías. El recibo se emite como cualquier otro, con el cliente como titular.
   - La venta se anula completa, nunca por cargo (`charges.void` y `payments.refund`, supervisor y administrador): vuelven las existencias (`sale_return`) y se devuelve lo cobrado por el mismo método; el efectivo sale de una caja abierta. Venta y anulación quedan auditadas.
3. Catálogo de empresas (razón social, NIT, contacto), cada una al contado o a crédito.
4. Grupos: una cuenta del grupo o empresa recibe las noches de sus habitaciones; los consumos los paga cada huésped en su cuenta.
5. Cuentas por cobrar de las empresas a crédito.

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
| 5 | Cuentas compartidas (`folios`) | Se introducen en la fase 4 | Cargos, pagos y recibos pertenecen a una cuenta; cada estadía tiene la suya. |
| 6 | Late check-out | Manual | Cargo `late_check_out` registrado por un operador con permiso. |
| 7 | Umbrales de caja y gastos | Configurables por sucursal, con valor global por defecto | Columnas en `branches` y `config/lodging.php`. |
| 8 | Representación del dinero | `DECIMAL(12,2)` + `BcMath\Number` | Migraciones, casts `decimal:2` y servicios. |
| 9 | Paquetes | `spatie/laravel-permission` (teams) y `spatie/laravel-activitylog` aprobados | Secciones 3.1 y 3.2. |
| 10 | Interfaz | Blade + Tailwind CSS | Sección 9. |
| 11 | Conservación de datos de huéspedes | Indefinida | Cifrado del documento y control de acceso estricto. |
| 12 | Facturación | Externa al sistema | Solo recibos internos y `external_invoice_number` (sección 3.7). |
| 13 | Reservas | El negocio no toma reservas anticipadas | Sin reservas ni disponibilidad por fechas; todo ingreso es directo. |
| 14 | Empresas y grupos | Catálogo de empresas; cada una paga al contado o a crédito | Fase 4: la cuenta del grupo se salda al salir o queda como cuenta por cobrar. |
| 15 | Qué paga el grupo | Solo el alojamiento | Las noches van a la cuenta del grupo; los consumos, a la cuenta de cada estadía. |

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
