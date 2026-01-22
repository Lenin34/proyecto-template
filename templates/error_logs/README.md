# Error Logs Visualization

Este módulo proporciona herramientas para visualizar los logs de errores de la aplicación, tanto en la interfaz web como en la aplicación móvil React Native.

## Características

- Visualización de archivos de log disponibles
- Visualización del contenido de los logs con filtrado por nivel y búsqueda
- Visualización de los últimos errores de todos los logs
- Paginación para navegar por archivos de log grandes
- Actualización automática de los últimos errores
- Interfaz web y componente React Native

## Interfaz Web

### Acceso

La interfaz web de visualización de logs está disponible en la siguiente URL:

```
/{dominio}/admin/logs
```

También se puede acceder desde el menú de navegación principal, haciendo clic en "LOGS".

### Uso

1. **Archivos de Log**: En el panel izquierdo se muestran todos los archivos de log disponibles. Haga clic en un archivo para ver su contenido.

2. **Contenido del Log**: En el panel derecho se muestra el contenido del archivo de log seleccionado. Puede filtrar el contenido por nivel de log (error, warning, notice, info, debug) y buscar texto específico.

3. **Últimos Errores**: En la parte inferior se muestran los últimos errores de todos los archivos de log. Esta sección se actualiza automáticamente cada 30 segundos.

## Componente React Native

### Instalación

1. Copie el archivo `ErrorLogsScreen.js` a su proyecto React Native.
2. Asegúrese de tener instaladas las dependencias necesarias:

```bash
npm install @react-navigation/native axios
```

### Integración

1. Importe el componente en su archivo de navegación:

```javascript
import ErrorLogsScreen from './path/to/ErrorLogsScreen';
```

2. Agregue la pantalla a su navegador:

```javascript
<Stack.Screen 
  name="ErrorLogs" 
  component={ErrorLogsScreen} 
  options={{ title: 'Logs de Errores' }} 
/>
```

3. Navegue a la pantalla pasando el dominio como parámetro:

```javascript
navigation.navigate('ErrorLogs', { dominio: 'su-dominio' });
```

### Personalización

Puede personalizar la apariencia del componente modificando el objeto `styles` en el archivo `ErrorLogsScreen.js`.

## API

El módulo utiliza los siguientes endpoints de API:

### Obtener archivos de log

```
GET /{dominio}/api/errors/files
```

Respuesta:
```json
{
  "files": [
    {
      "name": "dev.log",
      "path": "dev.log",
      "size": 1024,
      "modified": 1621234567
    }
  ]
}
```

### Obtener contenido de un archivo de log

```
GET /{dominio}/api/errors/file/{filename}
```

Parámetros de consulta:
- `level`: Filtrar por nivel de log (error, warning, notice, info, debug)
- `search`: Buscar texto específico
- `limit`: Número máximo de líneas a devolver (por defecto: 100)
- `offset`: Número de líneas a omitir (para paginación)

Respuesta:
```json
{
  "filename": "dev.log",
  "total_lines": 1000,
  "lines": ["[2023-05-01 12:34:56] app.ERROR: Error message"],
  "limit": 100,
  "offset": 0
}
```

### Obtener últimos errores

```
GET /{dominio}/api/errors/latest
```

Parámetros de consulta:
- `limit`: Número máximo de errores a devolver (por defecto: 50)
- `level`: Nivel de log a buscar (por defecto: error)

Respuesta:
```json
{
  "errors": [
    {
      "file": "dev.log",
      "content": "[2023-05-01 12:34:56] app.ERROR: Error message"
    }
  ],
  "count": 1
}
```

## Seguridad

El acceso a los logs de errores está restringido a usuarios autenticados con los permisos adecuados. Asegúrese de proteger adecuadamente estas rutas en su aplicación.