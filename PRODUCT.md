# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

Personal de un hotel con varias sucursales en Bolivia. Todo el trabajo se hace desde la PC de recepción (computadora de escritorio o laptop en el mostrador). Parte del personal tiene poca experiencia con sistemas.

Cada usuario tiene un rol por sucursal, y el rol define lo que ve y puede hacer:

- **Recepción:** ingresos (check-in), cambios de habitación, salidas (check-out), cargos, cobros, ventas de mostrador, apertura y cierre de caja, estado de limpieza.
- **Supervisión:** lo de recepción más descuentos, anulaciones, devoluciones, aprobación de salidas con deuda, revisión de diferencias de caja y ajustes de inventario.
- **Administración de sucursal:** configuración de la sucursal (habitaciones, tarifas, cajas, almacenes, personal), reportes y registro de auditoría.
- **Limpieza:** solo el estado de limpieza de las habitaciones.
- **Almacén:** inventario, transferencias y activos fijos.
- **Superadministración:** sucursales, usuarios y catálogos globales (productos y categorías).

## Product Purpose

Administrar la operación diaria del hotel en cada sucursal: estadías con ingreso directo (sin reservas anticipadas), cargos noche a noche, cobros, caja compartida, gastos, inventario y activos fijos, con reportes financieros. Éxito significa que el dinero cuadra: nada se cobra dos veces, nada se pierde, cada operación queda auditada y la caja se cierra con su diferencia explicada.

## Positioning

Herramienta interna hecha para la operación de este hotel en Bolivia, no un producto comercial: bolivianos (Bs) como única moneda, pago QR, CI con complemento, zona horaria de La Paz, fecha de negocio con auditoría nocturna y facturación fiscal fuera del sistema (solo recibos internos).

## Operating Context

- Mostrador de recepción con huéspedes esperando, turnos que se pasan la caja compartida y trabajo de noche.
- La fecha de negocio se cierra cada noche a la hora de corte (04:00 por defecto) y genera las noches de alojamiento.
- La factura fiscal se emite en un sistema externo; aquí se registra solo su número.
- Cada pantalla trabaja sobre la sucursal activa; los registros de otra sucursal no se ven.

## Capabilities and Constraints

- Laravel 13 con Blade y Tailwind CSS v4 renderizados en el servidor, MySQL y JavaScript mínimo sin framework. Agregar dependencias requiere aprobación.
- Interfaz solo en español. Términos del dominio: estadía, ingreso, salida, cargo, cobro, anticipo, devolución, caja, sesión de caja, arqueo, almacén, transferencia, activo fijo, cuenta (folio), venta de mostrador, salida con deuda.
- Las operaciones de dinero son irreversibles salvo por anulación con motivo; los formularios de dinero protegen contra doble envío.
- Cuentas de grupos y empresas (contado o crédito) y cuentas por cobrar: planificadas, todavía sin construir.
- Sin decidir: campos obligatorios del registro de huéspedes ante la Policía y Migración, valores definitivos de los umbrales de caja y gastos, y métodos de pago aceptados por sucursal.

## Brand Commitments

Sin marca por ahora: es una herramienta interna llamada "Alojamiento". No hay logo, colores ni tipografía comprometidos.

## Evidence on Hand

- Documento de diseño del sistema: `docs/resumen_sistema_alojamiento.md`.
- Datos de demostración: `database/seeders/DemoSeeder.php`.
- No hay testimonios, clientes, métricas ni capturas de uso real; no inventarlos.

## Product Principles

1. **Claridad para quien no es experto.** Pocos pasos, etiquetas en lenguaje del mostrador y una sola acción principal por tarjeta; el sistema explica qué pasará antes de mover dinero o existencias.
2. **El dinero no se equivoca.** Las operaciones financieras muestran importes y saldos explícitos, piden confirmación donde son irreversibles y nunca se duplican.
3. **Rapidez en el mostrador.** Las tareas frecuentes (ingreso, cobro, salida, venta) se resuelven sin navegar por varias pantallas.
4. **Cada quien ve lo suyo.** La interfaz muestra solo lo que el rol y la sucursal activa permiten.
5. **Todo queda registrado.** Cada operación sensible deja rastro de quién, cuándo y por qué.
