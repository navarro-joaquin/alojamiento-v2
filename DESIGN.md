---
name: Alojamiento
description: Sistema de recepción hotelera tratado con el cuidado de un billete boliviano.
colors:
  ink-sidebar: "#24305e"
  ink-deep: "#1b2449"
  ink-night: "#121935"
  ink-hover: "#2d3866"
  ink-mist: "#d9ddec"
  ink-wash: "#eef0f7"
  denom-violet: "#6a3d8f"
  denom-violet-deep: "#552f74"
  denom-focus: "#7a4ba3"
  denom-selection: "#d6c2e8"
  security-paper: "#f4f1ea"
  card-paper: "#fffdf8"
  field-white: "#ffffff"
  paper-rule: "#ddd6c7"
  paper-stroke: "#c6bdaa"
  paper-tag: "#ebe6db"
  muted-ink: "#545049"
  label-ink: "#3e3b37"
  body-ink: "#1c2340"
  free-teal: "#2b7a6c"
  free-teal-deep: "#1d574d"
  free-teal-wash: "#e9f4f1"
  occupied-blue: "#2a5aa8"
  occupied-blue-deep: "#1f437e"
  occupied-blue-wash: "#eaf0fa"
  dirty-orange: "#c2512a"
  dirty-orange-deep: "#8a3519"
  dirty-orange-wash: "#fbefe8"
  alert-red: "#b3261e"
  alert-red-deep: "#7f1a15"
  alert-red-wash: "#fbeceb"
typography:
  display:
    fontFamily: "Fraunces, Georgia, 'Times New Roman', serif"
    fontSize: "2.625rem"
    fontWeight: 600
    lineHeight: 1.2
    letterSpacing: "-0.025em"
  figures:
    fontFamily: "Fraunces, Georgia, 'Times New Roman', serif"
    fontSize: "2.25rem"
    fontWeight: 600
    lineHeight: 1
    fontFeature: "'lnum' 1, 'tnum' 1"
  title:
    fontFamily: "Fraunces, Georgia, 'Times New Roman', serif"
    fontSize: "1.25rem"
    fontWeight: 600
    lineHeight: 1.4
  body:
    fontFamily: "'Public Sans', ui-sans-serif, system-ui, sans-serif"
    fontSize: "1rem"
    fontWeight: 400
    lineHeight: 1.5
  label:
    fontFamily: "'Public Sans', ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.9375rem"
    fontWeight: 600
    lineHeight: 1.45
  caption:
    fontFamily: "'Public Sans', ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.875rem"
    fontWeight: 600
    lineHeight: 1.35
rounded:
  lg: "8px"
  xl: "12px"
  full: "9999px"
spacing:
  xs: "8px"
  sm: "12px"
  md: "16px"
  lg: "20px"
  xl: "24px"
  2xl: "40px"
components:
  button-primary:
    backgroundColor: "{colors.denom-violet}"
    textColor: "{colors.field-white}"
    typography: "{typography.label}"
    rounded: "{rounded.lg}"
    padding: "10px 16px"
  button-primary-hover:
    backgroundColor: "{colors.denom-violet-deep}"
  button-secondary:
    backgroundColor: "{colors.field-white}"
    textColor: "{colors.ink-sidebar}"
    typography: "{typography.label}"
    rounded: "{rounded.lg}"
    padding: "10px 16px"
  button-secondary-hover:
    backgroundColor: "{colors.ink-wash}"
  card:
    backgroundColor: "{colors.card-paper}"
    rounded: "{rounded.xl}"
    padding: "20px"
  input:
    backgroundColor: "{colors.field-white}"
    textColor: "{colors.body-ink}"
    typography: "{typography.body}"
    rounded: "{rounded.lg}"
    padding: "10px 12px"
  nav-link:
    textColor: "{colors.ink-mist}"
    typography: "{typography.body}"
    rounded: "{rounded.lg}"
    padding: "8px 12px"
  nav-link-hover:
    backgroundColor: "{colors.ink-hover}"
    textColor: "{colors.field-white}"
  nav-link-active:
    backgroundColor: "{colors.denom-violet}"
    textColor: "{colors.field-white}"
  badge-free:
    backgroundColor: "{colors.free-teal-wash}"
    textColor: "{colors.free-teal-deep}"
    typography: "{typography.caption}"
    rounded: "{rounded.full}"
    padding: "2px 10px"
  badge-occupied:
    backgroundColor: "{colors.occupied-blue-wash}"
    textColor: "{colors.occupied-blue-deep}"
    typography: "{typography.caption}"
    rounded: "{rounded.full}"
    padding: "2px 10px"
  badge-dirty:
    backgroundColor: "{colors.dirty-orange-wash}"
    textColor: "{colors.dirty-orange-deep}"
    typography: "{typography.caption}"
    rounded: "{rounded.full}"
    padding: "2px 10px"
  badge-alert:
    backgroundColor: "{colors.alert-red-wash}"
    textColor: "{colors.alert-red-deep}"
    typography: "{typography.caption}"
    rounded: "{rounded.full}"
    padding: "2px 10px"
  badge-neutral:
    backgroundColor: "{colors.paper-tag}"
    textColor: "{colors.label-ink}"
    typography: "{typography.caption}"
    rounded: "{rounded.full}"
    padding: "2px 10px"
---

# Design System: Alojamiento

## Overview

**Creative North Star: "El billete boliviano"**

Cada pantalla del mostrador se trata como un billete: tinta azul profunda, papel de seguridad marfil, grabado fino y un color por valor. La barra lateral es la franja de tinta (azul #24305e con texto marfil); el área de trabajo es el papel; las tarjetas son hojas de papel cálido con filete fino; y el morado de denominación aparece solo donde algo está seleccionado o es la acción principal. Los títulos, los números de habitación y las cifras grandes se componen en una serif grabada (Fraunces) con cifras alineadas y tabulares, como la numeración impresa de un billete.

La densidad es de herramienta de trabajo: cuadrículas de tarjetas, formularios directos, nada decorativo salvo un único gesto por página, el filete de guilloché bajo el título. Ninguna letra del sistema baja de 14 px: la escala de Tailwind se reescribió para que `xs` sea 14 px y `sm` 15 px, porque quien atiende el mostrador no debe forzar la vista. Los grises de Tailwind se redefinieron como tonos de papel y tinta, de modo que cualquier pantalla que use la escala gris hereda el mundo sin tocarla.

El estado de una habitación se lee por color, como el valor de un billete se lee por su color: verde azulado para disponible, azul Bs 10 para ocupada, naranja Bs 20 para sucia, rojo Bs 100 para fuera de servicio. El sistema rechaza la plantilla gris con un acento azul genérico que tenía antes.

**Key Characteristics:**
- Barra lateral de tinta azul fija a la izquierda; fondo de papel marfil a la derecha.
- Morado de denominación reservado para selección y acción principal.
- Serif grabada para títulos, números de habitación e importes; sans humanista para todo lo demás.
- Filete de guilloché, uno por página, bajo el título.
- Estado codificado por color de denominación en filete superior, cifra y pastilla.
- Mínimo de 14 px en todo texto.

## Colors

Paleta de billete: tinta azul y papel marfil como base, un morado de denominación escaso y cuatro colores de estado tomados de los billetes bolivianos.

### Primary
- **Morado de denominación** (denom-violet): botón principal («Registrar ingreso»), enlace activo de la navegación, color de acento nativo de casillas y cursor de texto, y la tinta del filete de guilloché. Su versión profunda (denom-violet-deep) es el hover del botón principal; denom-focus dibuja el anillo de foco de 2 px; denom-selection es el fondo de la selección de texto.

### Secondary
- **Tinta de la barra** (ink-sidebar): fondo de la barra lateral, de la barra superior en móvil y de la pantalla de ingreso; también el texto de los botones secundarios.
- **Tinta profunda** (ink-deep): títulos de página y de tarjeta, y el bloque de sucursal activa dentro de la barra.
- **Tinta nocturna** (ink-night): hover del bloque de sucursal y tinte de las sombras sobre la barra.
- **Tinta de hover** (ink-hover): hover de los enlaces de navegación y líneas divisorias dentro de la barra.
- **Niebla de tinta** (ink-mist): texto de los enlaces inactivos sobre la barra.
- **Lavado de tinta** (ink-wash): hover de los botones secundarios.

### Tertiary (colores de estado)
- **Verde azulado disponible** (free-teal, free-teal-deep, free-teal-wash): habitación libre y limpia.
- **Azul ocupada** (occupied-blue, occupied-blue-deep, occupied-blue-wash): habitación con estadía activa; el azul del billete de Bs 10.
- **Naranja sucia** (dirty-orange, dirty-orange-deep, dirty-orange-wash): habitación pendiente de limpieza; el naranja del billete de Bs 20.
- **Rojo alerta** (alert-red, alert-red-deep, alert-red-wash): fuera de servicio o mantenimiento; el rojo del billete de Bs 100.

Cada estado tiene tres usos fijos: el tono 600 es el filete superior de 5 px de la placa de habitación; el tono 800 (deep) colorea la cifra grande y el texto de la pastilla; el tono 50 (wash) es el fondo de la pastilla, con anillo interior en el tono 200.

### Neutral
- **Papel de seguridad** (security-paper): fondo de toda el área de trabajo.
- **Papel de tarjeta** (card-paper): fondo de tarjetas, placas de habitación, cifras de resumen y el panel de ingreso; un blanco cálido, distinto del blanco puro.
- **Blanco de campo** (field-white): solo campos de formulario y botones secundarios, para que lo editable se distinga del papel.
- **Filete de papel** (paper-rule): borde de 1 px de tarjetas.
- **Trazo de papel** (paper-stroke): borde de campos y botones secundarios; color de la barra de desplazamiento.
- **Etiqueta de papel** (paper-tag): fondo de la pastilla neutra («Limpia», «No disponible»).
- **Tinta apagada** (muted-ink): texto secundario, subtítulos de placa, etiquetas de resumen.
- **Tinta de etiqueta** (label-ink): etiquetas de formulario y texto de pastilla neutra.
- **Tinta de cuerpo** (body-ink): texto principal sobre papel.

### Named Rules
**The Denominación Rule.** El morado aparece solo en lo seleccionado (enlace activo, selección, casillas) y en la acción principal de cada tarjeta o formulario. Nunca como fondo decorativo, nunca en texto de lectura.

**The Valor por Color Rule.** Los estados de habitación usan exclusivamente los tokens free, occupied, dirty y alert. El morado no es un estado.

**The Papel y Tinta Rule.** La escala gris de Tailwind ya es papel y tinta; usar `gray-*` está permitido y hereda el mundo. No se introducen grises fríos ni otras escalas neutras.

## Typography

**Display Font:** Fraunces (con Georgia, Times New Roman, serif), pesos 500 a 700.
**Body Font:** Public Sans (con ui-sans-serif, system-ui, sans-serif), pesos 400 a 700.

Ambas se sirven desde el propio proyecto (fuentes Bunny autoalojadas mediante el plugin de Vite de Laravel).

**Character:** Fraunces aporta el grabado: cifras con presencia, como la numeración de un billete. Public Sans es la voz del mostrador, neutra y legible para quien no es experto.

### Hierarchy
- **Display** (Fraunces 600, 42 px en escritorio y 30 px en móvil, interletraje ajustado): el título de página, uno por pantalla, con equilibrio de líneas.
- **Figures** (Fraunces 600, 36 px, interlineado 1, cifras alineadas y tabulares): números de habitación y cifras del resumen; la utilidad `figures` aplica la familia y las cifras a cualquier importe.
- **Title** (Fraunces 600, 20 px): título de tarjeta.
- **Body** (Public Sans 400, 16 px, interlineado 1.5): texto general, valores de campos y enlaces de navegación.
- **Label** (Public Sans 600, 15 px): botones y etiquetas de formulario.
- **Caption** (Public Sans 600, 14 px): pastillas y rótulos de grupo de la barra. Es el mínimo absoluto; subtítulos y metadatos usan 15 px en peso normal.

### Named Rules
**The Catorce Rule.** Nada baja de 14 px. La escala `text-xs` vale 14 px y `text-sm` 15 px; no se introducen tamaños arbitrarios menores.

**The Cifra Grabada Rule.** Todo número que se lee de un vistazo (habitación, conteo, importe destacado) va en Fraunces con cifras tabulares. Las tablas aplican cifras tabulares por defecto.

## Layout

En escritorio (desde 1024 px) el cuerpo es una fila: barra lateral de 272 px fija en altura de pantalla, con desplazamiento propio, y área principal centrada con ancho máximo de 1280 px y márgenes de 40 px laterales y 32 px verticales. En móvil la barra se colapsa a una franja superior de tinta con el nombre y un botón «Menú» que despliega la navegación a ancho completo sin JavaScript; el área principal usa márgenes de 16 px y 24 px.

Cada página abre con un encabezado: título display seguido del filete de guilloché, separados 12 px. El ritmo vertical entre bloques es de 24 px; entre tarjetas de una cuadrícula, 16 px. Las placas de habitación forman una cuadrícula de 1, 2, 3 y 4 columnas según el ancho; el resumen de cifras, 2 columnas en móvil y 4 desde 640 px. El relleno interno de tarjeta es de 20 px.

En impresión se ocultan la barra lateral, la franja móvil, el filete y los mensajes de estado.

## Elevation & Depth

El sistema es plano con un susurro de papel: la profundidad la dan el borde fino y el contraste entre papel marfil, papel de tarjeta y blanco de campo, no las sombras. Las tarjetas llevan una sombra ambiental casi imperceptible teñida de tinta. Las únicas sombras más marcadas aparecen sobre fondo de tinta (el panel de ingreso y el enlace activo) y bajo el botón principal.

### Shadow Vocabulary
- **Hoja de papel** (`box-shadow: 0 1px 3px rgb(28 35 64 / 0.06)`): tarjetas, placas y cifras de resumen.
- **Botón acuñado** (sombra pequeña de Tailwind teñida con denom-900 al 20 %): botón principal.
- **Pastilla activa** (sombra pequeña teñida con ink-950 al 30 %): enlace activo sobre la barra.
- **Panel sobre tinta** (sombra extra grande teñida con ink-950 al 30 %): solo el panel de la pantalla de ingreso.

### Named Rules
**The Papel Plano Rule.** Sobre papel, la profundidad es borde y tono. No se añaden sombras más fuertes que la hoja de papel a tarjetas o paneles del área de trabajo.

## Shapes

Esquinas suavemente redondeadas y consistentes: 8 px para todo lo interactivo (botones, campos, enlaces de navegación, avisos) y 12 px para contenedores (tarjetas, placas, panel de ingreso). Las pastillas de estado son completamente redondeadas. Los bordes son de 1 px; la única excepción es el filete superior de 5 px de color de estado en las placas de habitación y el borde de 2 px rojo alerta que resalta la cifra de «Fuera de servicio» cuando es mayor que cero.

El filete de guilloché es la forma firma: una banda de 14 px entre dos líneas moradas de 1 px, rellena con dos ondas entrelazadas y dos ondas tenues que se repiten cada 40 px, como el borde de un billete.

## Components

### Buttons
Acuñados y directos: un solo botón morado por tarjeta.
- **Shape:** esquinas de 8 px.
- **Primary:** fondo morado de denominación, texto blanco, etiqueta 15 px semibold, relleno 10 px por 16 px, sombra de botón acuñado. Hover a morado profundo en 150 ms.
- **Secondary:** fondo blanco de campo, borde de trazo de papel, texto tinta de la barra. Hover: borde ink-300 y fondo lavado de tinta.
- **Focus:** anillo de 2 px denom-focus separado 2 px.
- **Disabled:** opacidad 50 % y cursor no permitido.
- **Enlace con forma de botón:** las acciones de navegación («Ver estadía») usan la forma secundaria con borde ink-200.

### Chips
- **Style:** pastilla completamente redondeada, caption 14 px semibold, relleno 2 px por 10 px, fondo wash del estado, texto deep y anillo interior del tono 200.
- **State:** cinco variantes: neutra (papel), disponible, ocupada, sucia y alerta. Son indicadores, no controles.

### Cards / Containers
- **Corner Style:** 12 px.
- **Background:** papel de tarjeta.
- **Shadow Strategy:** hoja de papel (ver Elevation & Depth).
- **Border:** 1 px filete de papel.
- **Internal Padding:** 20 px, con 16 px entre elementos internos; título opcional en serif 20 px.

### Inputs / Fields
- **Style:** fondo blanco puro, borde de 1 px trazo de papel, esquinas de 8 px, relleno 10 px por 12 px, valor en 16 px. La etiqueta va encima en 15 px semibold tinta de etiqueta, separada 6 px.
- **Focus:** contorno de 2 px denom-focus separado 1 px; el borde también pasa a morado.
- **Error:** borde rojo y mensaje debajo en peso normal (ver deriva en Do's and Don'ts).

### Navigation
- **Style:** barra de tinta de 272 px con nombre en Fraunces 24 px, bloque de sucursal activa y fecha de negocio sobre tinta profunda con anillo ink-hover, y enlaces de 16 px con esquinas de 8 px y relleno 8 px por 12 px.
- **States:** inactivo en niebla de tinta; hover con fondo ink-hover y texto blanco; activo con fondo morado de denominación, texto blanco semibold y `aria-current="page"`.
- **Grupos:** los grupos «Configuración» y «Administración» se encabezan con un rótulo de 14 px en mayúsculas con interletraje amplio en ink-200. Es un encabezado de sección de navegación, propio de la barra; no se usa sobre títulos de página.
- **Mobile:** franja superior de tinta con nombre y botón «Menú» con borde ink-500; la navegación se despliega a ancho completo.

### Placa de habitación
La pieza firma de Ocupación: tarjeta de papel con filete superior de 5 px del color de estado, número en cifra grabada de 36 px en el tono deep del mismo estado, tipo y capacidad en 15 px tinta apagada, pastillas de disponibilidad y limpieza, una única acción principal y, separado por una línea de papel, el cambio rápido de limpieza con su botón secundario.

### Filete de guilloché
Banda decorativa bajo el título de página y en el panel de ingreso; oculta a lectores de pantalla y en impresión. Uno por página.

## Do's and Don'ts

### Do:
- **Do** usar el morado de denominación solo para lo seleccionado y la acción principal; una acción principal por tarjeta o formulario.
- **Do** codificar el estado de una habitación con los tokens free, occupied, dirty y alert en sus tres usos: filete 600, cifra y texto 800, fondo 50.
- **Do** componer títulos de página, números de habitación e importes destacados en Fraunces con cifras tabulares.
- **Do** colocar el filete de guilloché una vez por página, bajo el título.
- **Do** mantener todo texto en 14 px o más.
- **Do** usar fondo blanco puro solo en campos editables; las superficies van en papel de tarjeta sobre papel de seguridad.

### Don't:
- **Don't** repetir el filete de guilloché dentro de tarjetas ni usarlo como separador genérico.
- **Don't** usar el morado como color de estado ni como fondo de bloques.
- **Don't** introducir grises fríos, otras escalas neutras ni el azul genérico de la plantilla anterior.
- **Don't** colocar rótulos en mayúsculas sobre títulos de página o de tarjeta; ese tratamiento es solo para los grupos de la barra lateral.
- **Don't** copiar los rojos y verdes por defecto de Tailwind (`red-*`, `emerald-*`) que aún quedan en botones de peligro, mensajes de error y avisos de estado: son deriva previa al mundo, no parte del sistema.
