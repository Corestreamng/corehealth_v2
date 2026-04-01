/// Post-processes recognized speech text:
/// - Expands common medical abbreviations
/// - Handles voice punctuation commands
/// - Capitalizes after sentence endings
class MedicalVocabulary {
  MedicalVocabulary._();

  /// Process a recognized text fragment.
  static String process(String text) {
    var result = text;
    result = _applyPunctuation(result);
    result = _expandAbbreviations(result);
    result = _capitalizeAfterSentenceEnd(result);
    return result;
  }

  // ─── Punctuation voice commands ───────────────────────────

  static final _punctuationMap = <String, String>{
    'period': '.',
    'full stop': '.',
    'comma': ',',
    'question mark': '?',
    'exclamation mark': '!',
    'exclamation point': '!',
    'colon': ':',
    'semicolon': ';',
    'new line': '\n',
    'newline': '\n',
    'new paragraph': '\n\n',
    'open parenthesis': '(',
    'close parenthesis': ')',
    'hyphen': '-',
    'dash': ' — ',
    'forward slash': '/',
  };

  static String _applyPunctuation(String text) {
    var result = text;
    for (final entry in _punctuationMap.entries) {
      result = result.replaceAll(
        RegExp(r'\b' + RegExp.escape(entry.key) + r'\b', caseSensitive: false),
        entry.value,
      );
    }
    // Clean up spaces before punctuation
    result = result.replaceAll(RegExp(r'\s+([.,?!;:])'), r'$1');
    return result;
  }

  // ─── Medical abbreviation expansion ───────────────────────

  static final _abbreviations = <String, String>{
    'bp': 'BP',
    'hr': 'HR',
    'rr': 'RR',
    'spo2': 'SpO2',
    'spo 2': 'SpO2',
    'ecg': 'ECG',
    'ekg': 'EKG',
    'ct': 'CT',
    'mri': 'MRI',
    'iv': 'IV',
    'im': 'IM',
    'sc': 'SC',
    'po': 'PO',
    'prn': 'PRN',
    'bid': 'BID',
    'tid': 'TID',
    'qid': 'QID',
    'od': 'OD',
    'stat': 'STAT',
    'cbc': 'CBC',
    'fbc': 'FBC',
    'lfts': 'LFTs',
    'lft': 'LFT',
    'rfts': 'RFTs',
    'rft': 'RFT',
    'wbc': 'WBC',
    'rbc': 'RBC',
    'hb': 'Hb',
    'hba1c': 'HbA1c',
    'bmi': 'BMI',
    'gcs': 'GCS',
    'dvt': 'DVT',
    'pe': 'PE',
    'uti': 'UTI',
    'copd': 'COPD',
    'mi': 'MI',
    'cva': 'CVA',
    'tia': 'TIA',
    'sob': 'SOB',
    'npo': 'NPO',
    'nkda': 'NKDA',
    'dm': 'DM',
    'htn': 'HTN',
    'ckd': 'CKD',
    'hiv': 'HIV',
    'tb': 'TB',
    'aids': 'AIDS',
    'icu': 'ICU',
    'er': 'ER',
    'or': 'OR',
    'esr': 'ESR',
    'crp': 'CRP',
    'pt': 'PT',
    'inr': 'INR',
    'aptt': 'APTT',
    'ast': 'AST',
    'alt': 'ALT',
    'bun': 'BUN',
    'egfr': 'eGFR',
    'tsh': 'TSH',
    'psa': 'PSA',
    'abg': 'ABG',
    'cxr': 'CXR',
    'uss': 'USS',
  };

  static String _expandAbbreviations(String text) {
    var result = text;
    for (final entry in _abbreviations.entries) {
      result = result.replaceAllMapped(
        RegExp(r'\b' + RegExp.escape(entry.key) + r'\b', caseSensitive: false),
        (m) => entry.value,
      );
    }
    return result;
  }

  // ─── Capitalization ───────────────────────────────────────

  static String _capitalizeAfterSentenceEnd(String text) {
    return text.replaceAllMapped(
      RegExp(r'([.!?]\s+)(\w)'),
      (m) => '${m.group(1)}${m.group(2)!.toUpperCase()}',
    );
  }
}
