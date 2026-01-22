import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  FlatList,
  StyleSheet,
  TouchableOpacity,
  ActivityIndicator,
  TextInput,
  RefreshControl,
  SafeAreaView,
  StatusBar,
  Platform,
  Picker,
  ScrollView
} from 'react-native';
import { useRoute } from '@react-navigation/native';
import axios from 'axios';

/**
 * Error Logs Screen Component for React Native
 * 
 * This component displays error logs from the API and allows filtering and viewing log details.
 */
const ErrorLogsScreen = ({ navigation }) => {
  const route = useRoute();
  const dominio = route.params?.dominio || 'default';
  
  // State variables
  const [logFiles, setLogFiles] = useState([]);
  const [selectedFile, setSelectedFile] = useState(null);
  const [logContent, setLogContent] = useState([]);
  const [latestErrors, setLatestErrors] = useState([]);
  const [loading, setLoading] = useState({
    files: true,
    content: false,
    errors: true
  });
  const [error, setError] = useState({
    files: null,
    content: null,
    errors: null
  });
  const [currentPage, setCurrentPage] = useState(0);
  const [totalPages, setTotalPages] = useState(0);
  const [pageSize] = useState(50);
  const [logLevel, setLogLevel] = useState('');
  const [searchTerm, setSearchTerm] = useState('');
  const [activeTab, setActiveTab] = useState('files');

  // API base URL
  const apiBaseUrl = `https://api.example.com/${dominio}/api/errors`;

  // Load log files
  const loadLogFiles = async () => {
    setLoading(prev => ({ ...prev, files: true }));
    setError(prev => ({ ...prev, files: null }));
    
    try {
      const response = await axios.get(`${apiBaseUrl}/files`);
      setLogFiles(response.data.files || []);
    } catch (err) {
      setError(prev => ({ 
        ...prev, 
        files: err.response?.data?.error || 'Error al cargar los archivos de log' 
      }));
    } finally {
      setLoading(prev => ({ ...prev, files: false }));
    }
  };

  // Load log file content
  const loadLogFileContent = async () => {
    if (!selectedFile) return;
    
    setLoading(prev => ({ ...prev, content: true }));
    setError(prev => ({ ...prev, content: null }));
    
    try {
      const params = {
        offset: currentPage * pageSize,
        limit: pageSize
      };
      
      if (logLevel) params.level = logLevel;
      if (searchTerm) params.search = searchTerm;
      
      const response = await axios.get(`${apiBaseUrl}/file/${selectedFile}`, { params });
      
      setLogContent(response.data.lines || []);
      setTotalPages(Math.ceil((response.data.total_lines || 0) / pageSize));
    } catch (err) {
      setError(prev => ({ 
        ...prev, 
        content: err.response?.data?.error || 'Error al cargar el contenido del log' 
      }));
    } finally {
      setLoading(prev => ({ ...prev, content: false }));
    }
  };

  // Load latest errors
  const loadLatestErrors = async () => {
    setLoading(prev => ({ ...prev, errors: true }));
    setError(prev => ({ ...prev, errors: null }));
    
    try {
      const response = await axios.get(`${apiBaseUrl}/latest`);
      setLatestErrors(response.data.errors || []);
    } catch (err) {
      setError(prev => ({ 
        ...prev, 
        errors: err.response?.data?.error || 'Error al cargar los últimos errores' 
      }));
    } finally {
      setLoading(prev => ({ ...prev, errors: false }));
    }
  };

  // Format file size
  const formatFileSize = (bytes) => {
    if (bytes === 0) return '0 Bytes';
    
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
  };

  // Determine log line style based on content
  const getLogLineStyle = (line) => {
    const lowerLine = line.toLowerCase();
    if (lowerLine.includes('error')) return styles.errorLine;
    if (lowerLine.includes('warning')) return styles.warningLine;
    if (lowerLine.includes('info')) return styles.infoLine;
    if (lowerLine.includes('debug')) return styles.debugLine;
    return {};
  };

  // Apply filters
  const applyFilters = () => {
    setCurrentPage(0);
    loadLogFileContent();
  };

  // Load data on component mount
  useEffect(() => {
    loadLogFiles();
    loadLatestErrors();
    
    // Refresh latest errors every 30 seconds
    const interval = setInterval(loadLatestErrors, 30000);
    return () => clearInterval(interval);
  }, []);

  // Load log content when selected file changes
  useEffect(() => {
    if (selectedFile) {
      setCurrentPage(0);
      loadLogFileContent();
    }
  }, [selectedFile]);

  // Render log file item
  const renderLogFileItem = ({ item }) => (
    <TouchableOpacity
      style={[
        styles.fileItem,
        selectedFile === item.name && styles.selectedFileItem
      ]}
      onPress={() => setSelectedFile(item.name)}
    >
      <Text style={styles.fileName}>{item.name}</Text>
      <Text style={styles.fileInfo}>
        {formatFileSize(item.size)} - {new Date(item.modified * 1000).toLocaleString()}
      </Text>
    </TouchableOpacity>
  );

  // Render log content item
  const renderLogContentItem = ({ item }) => (
    <View style={styles.logLine}>
      <Text style={[styles.logText, getLogLineStyle(item)]}>{item}</Text>
    </View>
  );

  // Render latest error item
  const renderLatestErrorItem = ({ item }) => (
    <View style={styles.errorItem}>
      <Text style={styles.errorItemTitle}>{item.file}</Text>
      <Text style={[styles.logText, styles.errorLine]}>{item.content}</Text>
    </View>
  );

  // Render loading indicator
  const renderLoading = (loadingState, message) => (
    <View style={styles.loadingContainer}>
      <ActivityIndicator size="large" color="#0066cc" />
      <Text style={styles.loadingText}>{message}</Text>
    </View>
  );

  // Render error message
  const renderError = (errorMessage) => (
    <View style={styles.errorContainer}>
      <Text style={styles.errorText}>{errorMessage}</Text>
      <TouchableOpacity
        style={styles.retryButton}
        onPress={() => {
          if (activeTab === 'files') loadLogFiles();
          else if (activeTab === 'content') loadLogFileContent();
          else loadLatestErrors();
        }}
      >
        <Text style={styles.retryButtonText}>Reintentar</Text>
      </TouchableOpacity>
    </View>
  );

  // Render files tab
  const renderFilesTab = () => (
    <View style={styles.tabContent}>
      {loading.files ? (
        renderLoading(loading.files, 'Cargando archivos de log...')
      ) : error.files ? (
        renderError(error.files)
      ) : logFiles.length === 0 ? (
        <Text style={styles.emptyText}>No hay archivos de log disponibles</Text>
      ) : (
        <FlatList
          data={logFiles}
          renderItem={renderLogFileItem}
          keyExtractor={(item) => item.name}
          refreshControl={
            <RefreshControl refreshing={loading.files} onRefresh={loadLogFiles} />
          }
        />
      )}
    </View>
  );

  // Render content tab
  const renderContentTab = () => (
    <View style={styles.tabContent}>
      <View style={styles.filterContainer}>
        <View style={styles.pickerContainer}>
          <Picker
            selectedValue={logLevel}
            style={styles.picker}
            onValueChange={(value) => setLogLevel(value)}
          >
            <Picker.Item label="Todos los niveles" value="" />
            <Picker.Item label="Error" value="error" />
            <Picker.Item label="Warning" value="warning" />
            <Picker.Item label="Notice" value="notice" />
            <Picker.Item label="Info" value="info" />
            <Picker.Item label="Debug" value="debug" />
          </Picker>
        </View>
        <TextInput
          style={styles.searchInput}
          placeholder="Buscar..."
          value={searchTerm}
          onChangeText={setSearchTerm}
        />
        <TouchableOpacity style={styles.filterButton} onPress={applyFilters}>
          <Text style={styles.filterButtonText}>Filtrar</Text>
        </TouchableOpacity>
      </View>

      {!selectedFile ? (
        <Text style={styles.emptyText}>Seleccione un archivo de log para ver su contenido</Text>
      ) : loading.content ? (
        renderLoading(loading.content, 'Cargando contenido del log...')
      ) : error.content ? (
        renderError(error.content)
      ) : logContent.length === 0 ? (
        <Text style={styles.emptyText}>No hay líneas que coincidan con los filtros</Text>
      ) : (
        <>
          <FlatList
            data={logContent}
            renderItem={renderLogContentItem}
            keyExtractor={(item, index) => `log-${index}`}
            style={styles.logContentList}
          />
          <View style={styles.paginationContainer}>
            <Text style={styles.paginationText}>
              Página {currentPage + 1} de {totalPages || 1}
            </Text>
            <View style={styles.paginationButtons}>
              <TouchableOpacity
                style={[styles.paginationButton, currentPage === 0 && styles.disabledButton]}
                onPress={() => {
                  if (currentPage > 0) {
                    setCurrentPage(currentPage - 1);
                    loadLogFileContent();
                  }
                }}
                disabled={currentPage === 0}
              >
                <Text style={styles.paginationButtonText}>Anterior</Text>
              </TouchableOpacity>
              <TouchableOpacity
                style={[styles.paginationButton, currentPage >= totalPages - 1 && styles.disabledButton]}
                onPress={() => {
                  if (currentPage < totalPages - 1) {
                    setCurrentPage(currentPage + 1);
                    loadLogFileContent();
                  }
                }}
                disabled={currentPage >= totalPages - 1}
              >
                <Text style={styles.paginationButtonText}>Siguiente</Text>
              </TouchableOpacity>
            </View>
          </View>
        </>
      )}
    </View>
  );

  // Render latest errors tab
  const renderLatestErrorsTab = () => (
    <View style={styles.tabContent}>
      {loading.errors ? (
        renderLoading(loading.errors, 'Cargando últimos errores...')
      ) : error.errors ? (
        renderError(error.errors)
      ) : latestErrors.length === 0 ? (
        <Text style={styles.emptyText}>No hay errores recientes</Text>
      ) : (
        <FlatList
          data={latestErrors}
          renderItem={renderLatestErrorItem}
          keyExtractor={(item, index) => `error-${index}`}
          refreshControl={
            <RefreshControl refreshing={loading.errors} onRefresh={loadLatestErrors} />
          }
        />
      )}
    </View>
  );

  return (
    <SafeAreaView style={styles.container}>
      <StatusBar barStyle="light-content" />
      
      <View style={styles.header}>
        <Text style={styles.headerTitle}>Logs de Errores</Text>
      </View>
      
      <View style={styles.tabBar}>
        <TouchableOpacity
          style={[styles.tabButton, activeTab === 'files' && styles.activeTabButton]}
          onPress={() => setActiveTab('files')}
        >
          <Text style={[styles.tabButtonText, activeTab === 'files' && styles.activeTabButtonText]}>
            Archivos
          </Text>
        </TouchableOpacity>
        <TouchableOpacity
          style={[styles.tabButton, activeTab === 'content' && styles.activeTabButton]}
          onPress={() => setActiveTab('content')}
        >
          <Text style={[styles.tabButtonText, activeTab === 'content' && styles.activeTabButtonText]}>
            Contenido
          </Text>
        </TouchableOpacity>
        <TouchableOpacity
          style={[styles.tabButton, activeTab === 'errors' && styles.activeTabButton]}
          onPress={() => setActiveTab('errors')}
        >
          <Text style={[styles.tabButtonText, activeTab === 'errors' && styles.activeTabButtonText]}>
            Últimos Errores
          </Text>
        </TouchableOpacity>
      </View>
      
      {activeTab === 'files' && renderFilesTab()}
      {activeTab === 'content' && renderContentTab()}
      {activeTab === 'errors' && renderLatestErrorsTab()}
    </SafeAreaView>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f5f5f5',
  },
  header: {
    backgroundColor: '#0066cc',
    padding: 15,
    alignItems: 'center',
    justifyContent: 'center',
  },
  headerTitle: {
    color: 'white',
    fontSize: 18,
    fontWeight: 'bold',
  },
  tabBar: {
    flexDirection: 'row',
    backgroundColor: '#ffffff',
    borderBottomWidth: 1,
    borderBottomColor: '#e0e0e0',
  },
  tabButton: {
    flex: 1,
    padding: 12,
    alignItems: 'center',
  },
  activeTabButton: {
    borderBottomWidth: 2,
    borderBottomColor: '#0066cc',
  },
  tabButtonText: {
    color: '#666666',
    fontWeight: '500',
  },
  activeTabButtonText: {
    color: '#0066cc',
    fontWeight: 'bold',
  },
  tabContent: {
    flex: 1,
    padding: 10,
  },
  fileItem: {
    backgroundColor: 'white',
    padding: 15,
    marginBottom: 8,
    borderRadius: 5,
    borderLeftWidth: 3,
    borderLeftColor: '#e0e0e0',
  },
  selectedFileItem: {
    borderLeftColor: '#0066cc',
    backgroundColor: '#f0f7ff',
  },
  fileName: {
    fontSize: 16,
    fontWeight: '500',
    marginBottom: 5,
  },
  fileInfo: {
    fontSize: 12,
    color: '#666666',
  },
  filterContainer: {
    flexDirection: 'row',
    marginBottom: 10,
    alignItems: 'center',
  },
  pickerContainer: {
    flex: 1,
    borderWidth: 1,
    borderColor: '#e0e0e0',
    borderRadius: 5,
    backgroundColor: 'white',
    marginRight: 5,
  },
  picker: {
    height: 40,
    width: '100%',
  },
  searchInput: {
    flex: 1,
    height: 40,
    borderWidth: 1,
    borderColor: '#e0e0e0',
    borderRadius: 5,
    paddingHorizontal: 10,
    backgroundColor: 'white',
    marginRight: 5,
  },
  filterButton: {
    backgroundColor: '#0066cc',
    paddingHorizontal: 15,
    paddingVertical: 10,
    borderRadius: 5,
  },
  filterButtonText: {
    color: 'white',
    fontWeight: '500',
  },
  logContentList: {
    flex: 1,
    backgroundColor: 'white',
    borderRadius: 5,
    borderWidth: 1,
    borderColor: '#e0e0e0',
  },
  logLine: {
    padding: 8,
    borderBottomWidth: 1,
    borderBottomColor: '#f0f0f0',
  },
  logText: {
    fontFamily: Platform.OS === 'ios' ? 'Menlo' : 'monospace',
    fontSize: 12,
  },
  errorLine: {
    color: '#dc3545',
    fontWeight: 'bold',
  },
  warningLine: {
    color: '#ffc107',
    fontWeight: 'bold',
  },
  infoLine: {
    color: '#0d6efd',
  },
  debugLine: {
    color: '#6c757d',
  },
  paginationContainer: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginTop: 10,
  },
  paginationText: {
    color: '#666666',
  },
  paginationButtons: {
    flexDirection: 'row',
  },
  paginationButton: {
    backgroundColor: '#0066cc',
    paddingHorizontal: 15,
    paddingVertical: 8,
    borderRadius: 5,
    marginLeft: 5,
  },
  disabledButton: {
    backgroundColor: '#cccccc',
  },
  paginationButtonText: {
    color: 'white',
    fontWeight: '500',
  },
  errorItem: {
    backgroundColor: 'white',
    padding: 15,
    marginBottom: 8,
    borderRadius: 5,
    borderLeftWidth: 3,
    borderLeftColor: '#dc3545',
  },
  errorItemTitle: {
    fontSize: 14,
    fontWeight: 'bold',
    marginBottom: 5,
  },
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
  },
  loadingText: {
    marginTop: 10,
    color: '#666666',
  },
  errorContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 20,
  },
  errorText: {
    color: '#dc3545',
    textAlign: 'center',
    marginBottom: 15,
  },
  retryButton: {
    backgroundColor: '#0066cc',
    paddingHorizontal: 20,
    paddingVertical: 10,
    borderRadius: 5,
  },
  retryButtonText: {
    color: 'white',
    fontWeight: '500',
  },
  emptyText: {
    textAlign: 'center',
    color: '#666666',
    marginTop: 20,
  },
});

export default ErrorLogsScreen;