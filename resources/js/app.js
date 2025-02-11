import React from 'react';
import SecurityAnalysisPDFDownload from './components/SecurityAnalysisPDFDownload';
import GamblingAnalysisPDFDownload from './components/GamblingAnalysisPDFDownload';

function AppS() {
  // Simulasi data hasil scanning backdoor
  const backdoorResult = {
    details: {
      suspicious_files: {
        php_backdoors: [
          "backdoor1.php",
          "malicious_script.php"
        ]
      },
      suspicious_content: {
        detected_strings: [
          "eval(base64_decode(...))",
          "shell_exec(...)"
        ]
      },
      site_info: {
        scanned_at: new Date().toISOString(),
        status: "Completed"
      }
    }
  };

  return (
    <div className="p-6">
      <h1 className="text-2xl font-bold mb-4">Security Scan Report</h1>
      <SecurityAnalysisPDFDownload backdoorResult={backdoorResult} />
    </div>
  );
}

function AppG() {
  // Simulasi data hasil scanning backdoor
  const gamblingResult = {
    analysis: {
      
    }
  };

  return (
    <div className="p-6">
      <h1 className="text-2xl font-bold mb-4">Gambling Scan Report</h1>
      <GamblingAnalysisPDFDownload gamblingResult={gamblingResult} />
    </div>
  );
}

export { AppS, AppG };
