import React from 'react';
import { PDFDownloadLink, Document, Page, Text, View, StyleSheet } from '@react-pdf/renderer';
import { Button } from '@/components/ui/button';
import { FileDown } from 'lucide-react';

// Create styles for PDF
const styles = StyleSheet.create({
  page: {
    padding: 30,
    backgroundColor: '#ffffff'
  },
  section: {
    marginBottom: 20
  },
  title: {
    fontSize: 24,
    marginBottom: 20,
    color: '#1e40af',
    fontWeight: 'bold'
  },
  heading: {
    fontSize: 18,
    marginBottom: 10,
    color: '#1e3a8a',
    fontWeight: 'bold'
  },
  subheading: {
    fontSize: 14,
    marginBottom: 8,
    color: '#374151',
    fontWeight: 'bold'
  },
  content: {
    fontSize: 12,
    marginBottom: 5,
    color: '#4b5563'
  },
  listItem: {
    marginLeft: 15,
    marginBottom: 3
  },
  timestamp: {
    fontSize: 10,
    color: '#6b7280',
    marginTop: 5
  }
});

// PDF Document component
const GamblingAnalysisPDF = ({ analysis }) => (
  <Document>
    <Page size="A4" style={styles.page}>
      <Text style={styles.title}>Gambling Analysis Report</Text>
      
      {Object.entries(analysis).map(([key, value]) => (
        <View key={key} style={styles.section}>
          <Text style={styles.heading}>
            {key.split('_').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ')}
          </Text>
          
          {Object.entries(value).map(([subKey, subValue]) => (
            <View key={subKey} style={{ marginBottom: 10 }}>
              <Text style={styles.subheading}>
                {subKey.split('_').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ')}
              </Text>
              
              {Array.isArray(subValue) ? (
                subValue.map((item, index) => (
                  <View key={index} style={styles.listItem}>
                    <Text style={styles.content}>
                      {typeof item === 'object' ? JSON.stringify(item, null, 2) : item}
                    </Text>
                  </View>
                ))
              ) : (
                <Text style={styles.content}>{subValue}</Text>
              )}
            </View>
          ))}
        </View>
      ))}
      
      <Text style={styles.timestamp}>
        Generated on: {new Date().toLocaleString()}
      </Text>
    </Page>
  </Document>
);

// Main component with download button
const GamblingAnalysisPDFDownload = ({ gamblingResult }) => {
  if (!gamblingResult?.analysis) {
    return <Text>No data available for PDF generation</Text>;
  }

  return (
    <PDFDownloadLink
      document={<GamblingAnalysisPDF details={gamblingResult.analysis} />}
      fileName="gambling-analysis.pdf"
    >
      {({ loading }) => (
        <Button 
          className="flex items-center gap-2" 
          disabled={loading}
        >
          <FileDown className="w-4 h-4" />
          {loading ? 'Generating PDF...' : 'Download PDF Report'}
        </Button>
      )}
    </PDFDownloadLink>
  );
};

export default GamblingAnalysisPDFDownload;