<?php

namespace App\Service;

use App\Entity\App\FormTemplate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class FormExportService
{
    private FormTemplateService $formTemplateService;
    private FormFieldService $formFieldService;
    private TenantLoggerService $logger;

    public function __construct(
        FormTemplateService $formTemplateService,
        FormFieldService $formFieldService,
        TenantLoggerService $logger
    ) {
        $this->formTemplateService = $formTemplateService;
        $this->formFieldService = $formFieldService;
        $this->logger = $logger;
    }

    /**
     * Exporta un formulario a JSON
     */
    public function exportToJson(int $formTemplateId): array
    {
        $formTemplate = $this->formTemplateService->getFormTemplateById($formTemplateId);
        $fields = $this->formFieldService->getActiveFieldsByFormTemplate($formTemplate);

        $exportData = [
            'form_template' => [
                'name' => $formTemplate->getName(),
                'description' => $formTemplate->getDescription(),
                'created_at' => $formTemplate->getCreatedAt()->format('Y-m-d H:i:s'),
                'export_version' => '1.0',
                'export_date' => (new \DateTime())->format('Y-m-d H:i:s')
            ],
            'fields' => []
        ];

        foreach ($fields as $field) {
            $exportData['fields'][] = [
                'label' => $field->getLabel(),
                'name' => $field->getName(),
                'type' => $field->getType(),
                'is_required' => $field->getIsRequired(),
                'options' => $field->getOptions(),
                'help' => $field->getHelp(),
                'multiple' => $field->getMultiple(),
                'cols' => $field->getCols(),
                'textarea_cols' => $field->getTextareaCols(),
                'position' => $field->getPosition()
            ];
        }

        $this->logger->info('Form exported to JSON', [
            'form_id' => $formTemplateId,
            'form_name' => $formTemplate->getName(),
            'fields_count' => count($fields)
        ]);

        return $exportData;
    }

    /**
     * Exporta un formulario a Excel
     */
    public function exportToExcel(int $formTemplateId): Response
    {
        $formTemplate = $this->formTemplateService->getFormTemplateById($formTemplateId);
        $fields = $this->formFieldService->getActiveFieldsByFormTemplate($formTemplate);

        $spreadsheet = new Spreadsheet();
        
        // Hoja 1: Información del formulario
        $this->createFormInfoSheet($spreadsheet, $formTemplate);
        
        // Hoja 2: Campos del formulario
        $this->createFieldsSheet($spreadsheet, $fields);
        
        // Hoja 3: Plantilla para datos
        $this->createDataTemplateSheet($spreadsheet, $fields);

        // Configurar respuesta
        $writer = new Xlsx($spreadsheet);
        $fileName = 'formulario_' . $this->sanitizeFileName($formTemplate->getName()) . '_' . date('Y-m-d') . '.xlsx';
        
        $response = new Response();
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $fileName
        ));

        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean();
        $response->setContent($content);

        $this->logger->info('Form exported to Excel', [
            'form_id' => $formTemplateId,
            'form_name' => $formTemplate->getName(),
            'file_name' => $fileName
        ]);

        return $response;
    }

    /**
     * Exporta múltiples formularios a un archivo ZIP
     */
    public function exportMultipleToZip(array $formTemplateIds): Response
    {
        $zip = new \ZipArchive();
        $zipFileName = 'formularios_' . date('Y-m-d_H-i-s') . '.zip';
        $tempPath = sys_get_temp_dir() . '/' . $zipFileName;

        if ($zip->open($tempPath, \ZipArchive::CREATE) !== TRUE) {
            throw new \RuntimeException('No se pudo crear el archivo ZIP');
        }

        foreach ($formTemplateIds as $formTemplateId) {
            try {
                $jsonData = $this->exportToJson($formTemplateId);
                $formTemplate = $this->formTemplateService->getFormTemplateById($formTemplateId);
                
                $fileName = 'formulario_' . $this->sanitizeFileName($formTemplate->getName()) . '.json';
                $zip->addFromString($fileName, json_encode($jsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                
            } catch (\Exception $e) {
                $this->logger->error('Error exporting form to ZIP', [
                    'form_id' => $formTemplateId,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $zip->close();

        $response = new Response(file_get_contents($tempPath));
        $response->headers->set('Content-Type', 'application/zip');
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $zipFileName
        ));

        // Limpiar archivo temporal
        unlink($tempPath);

        return $response;
    }

    /**
     * Crea la hoja de información del formulario
     */
    private function createFormInfoSheet(Spreadsheet $spreadsheet, FormTemplate $formTemplate): void
    {
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Información del Formulario');

        // Encabezados
        $sheet->setCellValue('A1', 'Información del Formulario');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('4472C4');
        $sheet->getStyle('A1')->getFont()->getColor()->setRGB('FFFFFF');

        // Datos del formulario
        $data = [
            ['Campo', 'Valor'],
            ['Nombre', $formTemplate->getName()],
            ['Descripción', $formTemplate->getDescription()],
            ['Fecha de Creación', $formTemplate->getCreatedAt()->format('Y-m-d H:i:s')],
            ['Última Actualización', $formTemplate->getUpdatedAt()->format('Y-m-d H:i:s')],
            ['Estado', $formTemplate->getStatus()->value],
            ['Fecha de Exportación', (new \DateTime())->format('Y-m-d H:i:s')]
        ];

        $row = 3;
        foreach ($data as $rowData) {
            $sheet->setCellValue('A' . $row, $rowData[0]);
            $sheet->setCellValue('B' . $row, $rowData[1]);
            
            if ($row === 3) {
                $sheet->getStyle('A' . $row . ':B' . $row)->getFont()->setBold(true);
                $sheet->getStyle('A' . $row . ':B' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9E2F3');
            }
            
            $row++;
        }

        // Ajustar ancho de columnas
        $sheet->getColumnDimension('A')->setWidth(20);
        $sheet->getColumnDimension('B')->setWidth(40);
    }

    /**
     * Crea la hoja de campos del formulario
     */
    private function createFieldsSheet(Spreadsheet $spreadsheet, array $fields): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Campos del Formulario');

        // Encabezados
        $headers = ['Posición', 'Etiqueta', 'Nombre', 'Tipo', 'Requerido', 'Opciones', 'Ayuda', 'Múltiple', 'Columnas', 'Filas Textarea'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', $header);
            $sheet->getStyle($col . '1')->getFont()->setBold(true);
            $sheet->getStyle($col . '1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('4472C4');
            $sheet->getStyle($col . '1')->getFont()->getColor()->setRGB('FFFFFF');
            $col++;
        }

        // Datos de los campos
        $row = 2;
        foreach ($fields as $field) {
            $sheet->setCellValue('A' . $row, $field->getPosition());
            $sheet->setCellValue('B' . $row, $field->getLabel());
            $sheet->setCellValue('C' . $row, $field->getName());
            $sheet->setCellValue('D' . $row, $field->getType());
            $sheet->setCellValue('E' . $row, $field->getIsRequired() ? 'Sí' : 'No');
            $sheet->setCellValue('F' . $row, $field->getOptions());
            $sheet->setCellValue('G' . $row, $field->getHelp());
            $sheet->setCellValue('H' . $row, $field->getMultiple() ? 'Sí' : 'No');
            $sheet->setCellValue('I' . $row, $field->getCols());
            $sheet->setCellValue('J' . $row, $field->getTextareaCols());
            $row++;
        }

        // Ajustar ancho de columnas
        foreach (range('A', 'J') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    /**
     * Crea una hoja plantilla para captura de datos
     */
    private function createDataTemplateSheet(Spreadsheet $spreadsheet, array $fields): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Plantilla de Datos');

        // Encabezados basados en los campos del formulario
        $col = 'A';
        foreach ($fields as $field) {
            $header = $field->getLabel();
            if ($field->getIsRequired()) {
                $header .= ' *';
            }
            
            $sheet->setCellValue($col . '1', $header);
            $sheet->getStyle($col . '1')->getFont()->setBold(true);
            $sheet->getStyle($col . '1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('70AD47');
            $sheet->getStyle($col . '1')->getFont()->getColor()->setRGB('FFFFFF');
            
            // Agregar comentario con información del campo
            $comment = $field->getHelp() ?: 'Tipo: ' . $field->getType();
            if ($field->getOptions()) {
                $comment .= "\nOpciones: " . $field->getOptions();
            }
            $sheet->getComment($col . '1')->getText()->createTextRun($comment);
            
            $col++;
        }

        // Ajustar ancho de columnas
        foreach (range('A', $col) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
    }

    /**
     * Sanitiza el nombre del archivo
     */
    private function sanitizeFileName(string $fileName): string
    {
        $fileName = preg_replace('/[^a-zA-Z0-9\-_]/', '_', $fileName);
        return substr($fileName, 0, 50);
    }
}
