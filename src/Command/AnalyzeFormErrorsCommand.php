<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:analyze-form-errors',
    description: 'Analiza los logs de errores de formularios para identificar patrones'
)]
class AnalyzeFormErrorsCommand extends Command
{
    private string $logDir;

    public function __construct(string $logDir = null)
    {
        parent::__construct();
        $this->logDir = $logDir ?: ($_ENV['KERNEL_LOGS_DIR'] ?? 'var/log');
    }

    protected function configure(): void
    {
        $this
            ->addOption('days', 'd', InputOption::VALUE_OPTIONAL, 'Número de días a analizar', 7)
            ->addOption('log-file', 'f', InputOption::VALUE_OPTIONAL, 'Archivo de log específico', 'prod.log')
            ->addOption('textarea-only', 't', InputOption::VALUE_NONE, 'Solo errores relacionados con textarea')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $days = (int) $input->getOption('days');
        $logFile = $input->getOption('log-file');
        $textareaOnly = $input->getOption('textarea-only');

        $io->title('Análisis de Errores de Formularios');

        $logPath = $this->logDir . '/' . $logFile;
        
        if (!file_exists($logPath)) {
            $io->error("Archivo de log no encontrado: $logPath");
            return Command::FAILURE;
        }

        $io->info("Analizando archivo: $logPath");
        $io->info("Período: últimos $days días");

        $analysis = $this->analyzeLogFile($logPath, $days, $textareaOnly);

        $this->displayResults($io, $analysis);

        return Command::SUCCESS;
    }

    private function analyzeLogFile(string $logPath, int $days, bool $textareaOnly): array
    {
        $cutoffDate = new \DateTime("-$days days");
        $lines = file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        $analysis = [
            'total_lines' => count($lines),
            'form_errors' => [],
            'textarea_errors' => [],
            'error_patterns' => [],
            'error_counts' => [],
            'timeline' => []
        ];

        foreach ($lines as $lineNumber => $line) {
            // Extraer fecha del log
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2})/', $line, $matches)) {
                $logDate = \DateTime::createFromFormat('Y-m-d\TH:i:s', $matches[1]);
                
                if ($logDate && $logDate >= $cutoffDate) {
                    $this->analyzeLine($line, $lineNumber, $analysis, $textareaOnly);
                }
            }
        }

        // Procesar patrones
        $this->processPatterns($analysis);

        return $analysis;
    }

    private function analyzeLine(string $line, int $lineNumber, array &$analysis, bool $textareaOnly): void
    {
        // Buscar errores de formularios
        if (strpos($line, '[FORM_ERROR]') !== false || strpos($line, '[FORM_DEBUG]') !== false) {
            $error = [
                'line' => $lineNumber + 1,
                'content' => $line,
                'type' => 'form_error'
            ];

            // Extraer información específica
            if (preg_match('/\[FORM_ERROR\].*?"error_message":"([^"]+)"/', $line, $matches)) {
                $error['message'] = $matches[1];
            }

            if (preg_match('/\[FORM_ERROR\].*?"form_id":"?(\d+)"?/', $line, $matches)) {
                $error['form_id'] = $matches[1];
            }

            $analysis['form_errors'][] = $error;
        }

        // Buscar errores específicos de textarea
        if (strpos($line, 'textarea') !== false || strpos($line, 'TEXTAREA') !== false) {
            $textareaError = [
                'line' => $lineNumber + 1,
                'content' => $line,
                'type' => 'textarea_error'
            ];

            if (preg_match('/field_name":"([^"]*textarea[^"]*)"/', $line, $matches)) {
                $textareaError['field_name'] = $matches[1];
            }

            $analysis['textarea_errors'][] = $textareaError;
        }

        // Solo continuar si no es textarea-only o si es un error de textarea
        if ($textareaOnly && strpos($line, 'textarea') === false) {
            return;
        }

        // Buscar patrones de error comunes
        $patterns = [
            'mysql_error' => '/MySQL.*?error/i',
            'encoding_error' => '/encoding|charset|utf-?8/i',
            'null_byte' => '/null.*?byte|\\\\0/i',
            'length_error' => '/too.*?long|length.*?exceed/i',
            'validation_error' => '/validation.*?fail/i',
            'database_error' => '/database.*?error|sql.*?error/i'
        ];

        foreach ($patterns as $patternName => $pattern) {
            if (preg_match($pattern, $line)) {
                if (!isset($analysis['error_patterns'][$patternName])) {
                    $analysis['error_patterns'][$patternName] = 0;
                }
                $analysis['error_patterns'][$patternName]++;
            }
        }

        // Contar errores por hora
        if (preg_match('/^\[(\d{4}-\d{2}-\d{2}T\d{2})/', $line, $matches)) {
            $hour = $matches[1];
            if (!isset($analysis['timeline'][$hour])) {
                $analysis['timeline'][$hour] = 0;
            }
            $analysis['timeline'][$hour]++;
        }
    }

    private function processPatterns(array &$analysis): void
    {
        // Ordenar patrones por frecuencia
        arsort($analysis['error_patterns']);
        
        // Ordenar timeline
        ksort($analysis['timeline']);

        // Contar errores por formulario
        $formCounts = [];
        foreach ($analysis['form_errors'] as $error) {
            if (isset($error['form_id'])) {
                $formId = $error['form_id'];
                if (!isset($formCounts[$formId])) {
                    $formCounts[$formId] = 0;
                }
                $formCounts[$formId]++;
            }
        }
        arsort($formCounts);
        $analysis['error_counts'] = $formCounts;
    }

    private function displayResults(SymfonyStyle $io, array $analysis): void
    {
        $io->section('Resumen General');
        $io->table(
            ['Métrica', 'Valor'],
            [
                ['Total de líneas analizadas', number_format($analysis['total_lines'])],
                ['Errores de formularios', count($analysis['form_errors'])],
                ['Errores de textarea', count($analysis['textarea_errors'])],
                ['Patrones únicos', count($analysis['error_patterns'])]
            ]
        );

        if (!empty($analysis['error_patterns'])) {
            $io->section('Patrones de Error Más Comunes');
            $patternData = [];
            foreach ($analysis['error_patterns'] as $pattern => $count) {
                $patternData[] = [$pattern, $count];
            }
            $io->table(['Patrón', 'Frecuencia'], array_slice($patternData, 0, 10));
        }

        if (!empty($analysis['error_counts'])) {
            $io->section('Formularios con Más Errores');
            $formData = [];
            foreach ($analysis['error_counts'] as $formId => $count) {
                $formData[] = ["Formulario ID: $formId", $count];
            }
            $io->table(['Formulario', 'Errores'], array_slice($formData, 0, 10));
        }

        if (!empty($analysis['textarea_errors'])) {
            $io->section('Errores de Textarea Recientes');
            foreach (array_slice($analysis['textarea_errors'], -5) as $error) {
                $io->text("Línea {$error['line']}: " . substr($error['content'], 0, 100) . '...');
            }
        }

        if (!empty($analysis['timeline'])) {
            $io->section('Distribución Temporal (últimas 24 horas)');
            $timelineData = [];
            $recent = array_slice($analysis['timeline'], -24, 24, true);
            foreach ($recent as $hour => $count) {
                $timelineData[] = [$hour, $count];
            }
            $io->table(['Hora', 'Errores'], $timelineData);
        }

        // Recomendaciones
        $io->section('Recomendaciones');
        $recommendations = $this->generateRecommendations($analysis);
        foreach ($recommendations as $recommendation) {
            $io->text("• $recommendation");
        }
    }

    private function generateRecommendations(array $analysis): array
    {
        $recommendations = [];

        if (isset($analysis['error_patterns']['encoding_error']) && $analysis['error_patterns']['encoding_error'] > 5) {
            $recommendations[] = 'Considere implementar validación de encoding UTF-8 más estricta';
        }

        if (isset($analysis['error_patterns']['null_byte']) && $analysis['error_patterns']['null_byte'] > 0) {
            $recommendations[] = 'Implemente sanitización para remover bytes nulos en campos textarea';
        }

        if (isset($analysis['error_patterns']['length_error']) && $analysis['error_patterns']['length_error'] > 3) {
            $recommendations[] = 'Agregue validación de longitud en el frontend para campos textarea';
        }

        if (count($analysis['textarea_errors']) > count($analysis['form_errors']) * 0.5) {
            $recommendations[] = 'Los errores de textarea son significativos - considere mejorar la validación específica';
        }

        if (empty($recommendations)) {
            $recommendations[] = 'No se detectaron patrones críticos. Continúe monitoreando.';
        }

        return $recommendations;
    }
}
