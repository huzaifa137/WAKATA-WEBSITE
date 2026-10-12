/* ==========================================================
   WAKATA — sample catalogue data (front-end only)
   Replace with API / Laravel (Eloquent -> JSON or Blade loops)
   once the backend exists. Prices are in UGX and are SAMPLE values.
   ========================================================== */
window.WK = (function () {
  const LEVELS = {
    ple:  { label: 'Primary (PLE)',    short: 'PLE',  book: 'primary' },
    uce:  { label: 'O-Level (UCE)',    short: 'UCE',  book: 'o' },
    uace: { label: 'A-Level (UACE)',   short: 'UACE', book: 'a' }
  };
  const BOOK_LEVELS = { primary: 'Primary', o: 'O-Level', a: 'A-Level' };

  /* Subject colours used for generated covers (c1 -> c2 gradient, c3 accent) */
  const SUBJECTS = {
    'Biology':        ['#D2452B', '#8E1D12', '#FFD43B'],
    'Chemistry':      ['#1F8A5B', '#0C4A33', '#FFE27A'],
    'Physics':        ['#6B3FA0', '#2E1B5B', '#FFD43B'],
    'Agriculture':    ['#7E8E1E', '#3C4709', '#FFFFFF'],
    'Mathematics':    ['#043AA0', '#061B4D', '#FFD43B'],
    'Geography':      ['#0E7C86', '#064049', '#FFE27A'],
    'History':        ['#A5622A', '#5B300F', '#FFE27A'],
    'English':        ['#B1324E', '#5E0F26', '#FFD43B'],
    'Literature':     ['#7A2E8E', '#3D1248', '#FFE27A'],
    'Art & Design':   ['#E07B00', '#8A3F00', '#FFFFFF'],
    'Entrepreneurship': ['#2D63CF', '#0B2E7A', '#FFD43B'],
    'Science':        ['#1F8A5B', '#0C4A33', '#FFD43B'],
    'Social Studies': ['#A5622A', '#5B300F', '#FFD43B'],
    'Economics':      ['#0E7C86', '#064049', '#FFD43B'],
    'General Paper':  ['#4A5A8A', '#1E2A52', '#FFD43B'],
    'Literacy':       ['#E8A200', '#9A6200', '#FFFFFF']
  };

  /* ---------------- BOOKS ---------------- */
  const IMG = (window.WK ? WK.u('assets/img') : 'assets/img') + '/books/';
  const books = [
    { id: 'bio-uce-practical', title: 'UCE Biology 553/2 & 553/3 Practical Workbook', subject: 'Biology', level: 'o', price: 28000, old: 32000, badge: 'Bestseller', img: IMG + 'biology-uce.jpg', full: IMG + 'biology-uce-full.jpg', pages: 148, edition: '2026 edition', type: 'Practical workbook', desc: 'Guided practical exercises for the Biology paper 2 and paper 3 practical exams, with worked observations, drawing guides and examiner tips written by classroom teachers.' },
    { id: 'bio-uclse-new', title: 'UCLSE New Curriculum Biology Practical Workbook', subject: 'Biology', level: 'o', price: 25000, badge: 'New curriculum', img: IMG + 'biology-uclse.jpg', full: IMG + 'biology-uclse-full.jpg', pages: 132, edition: 'New curriculum', type: 'Practical workbook', desc: 'Aligned to the lower secondary new curriculum. Step-by-step practical activities with space to record observations and answer competency-based questions.' },
    { id: 'phy-practicals-s123', title: 'Physics Practicals 535/3/4 for S.1, 2 & 3', subject: 'Physics', level: 'o', price: 20000, img: IMG + 'physics-practicals.jpg', full: IMG + 'physics-practicals-full.jpg', pages: 96, edition: 'Classic edition', type: 'Practical workbook', desc: 'Real science means practical. Tested experiments with apparatus lists, set-up diagrams and result tables for the first three years of secondary school.' },
    { id: 'phy-uclse-new', title: 'UCLSE New Curriculum Physics Practical Workbook', subject: 'Physics', level: 'o', price: 25000, badge: 'New curriculum', img: IMG + 'physics-uclse.jpg', full: IMG + 'physics-uclse-full.jpg', pages: 128, edition: 'New curriculum', type: 'Practical workbook', desc: 'Hands-on experiments for the new lower secondary curriculum, organised by topic with clear aims, procedures and review questions.' },
    { id: 'phonics-short-vowels', title: 'Phonics Workbook: Short Vowel Sounds', subject: 'Literacy', level: 'primary', price: 8000, badge: 'Lower primary', img: IMG + 'phonics-short-u.jpg', pages: 64, edition: '2026 edition', type: 'Activity workbook', desc: 'Colourful sound-by-sound worksheets that help young learners hear, say and write the short vowel sounds. Includes tracing, matching and word-building tasks.' },
    { id: 'chem-uce-practical', title: 'UCE Chemistry 545/3 Practical Workbook', subject: 'Chemistry', level: 'o', price: 28000, pages: 140, edition: '2026 edition', type: 'Practical workbook', desc: 'Qualitative analysis, titration and rates of reaction practicals with sample results tables and marking guidance.' },
    { id: 'chem-uclse-new', title: 'UCLSE New Curriculum Chemistry Practical Workbook', subject: 'Chemistry', level: 'o', price: 25000, badge: 'New curriculum', pages: 124, edition: 'New curriculum', type: 'Practical workbook', desc: 'Safe, simple laboratory activities for the new curriculum, with observation tables and short competency questions.' },
    { id: 'agric-uce-notes', title: 'UCE Agriculture Notes and Practicals', subject: 'Agriculture', level: 'o', price: 22000, pages: 176, edition: '2025 edition', type: 'Textbook', desc: 'Crop husbandry, animal production, farm tools and agricultural economics, with project guidance for the practical paper.' },
    { id: 'agric-uace', title: 'UACE Agriculture Revision Guide', subject: 'Agriculture', level: 'a', price: 35000, pages: 240, edition: '2025 edition', type: 'Revision guide', desc: 'Concise notes, diagrams and past-paper style questions covering the full A-Level syllabus.' },
    { id: 'math-uce-revision', title: 'UCE Mathematics Revision Questions (S.1 to S.4)', subject: 'Mathematics', level: 'o', price: 30000, old: 34000, badge: 'Popular', pages: 220, edition: '2026 edition', type: 'Revision book', desc: 'Topic-by-topic questions graded from easy to exam level, each with a full marking scheme and common-mistake notes.' },
    { id: 'math-uace-p1p2', title: 'UACE Mathematics Papers 1 & 2 Worked Solutions', subject: 'Mathematics', level: 'a', price: 38000, pages: 260, edition: '2025 edition', type: 'Revision guide', desc: 'Fully worked solutions to five years of exam questions, explained the way examiners expect them to be answered.' },
    { id: 'geo-uce-fieldwork', title: 'UCE Geography Map Work and Fieldwork', subject: 'Geography', level: 'o', price: 24000, pages: 150, edition: '2025 edition', type: 'Workbook', desc: 'Map reading, scale, gradient and fieldwork report writing, with Ugandan topographic examples.' },
    { id: 'hist-uce-east-africa', title: 'UCE History of East Africa Simplified', subject: 'History', level: 'o', price: 23000, pages: 190, edition: '2025 edition', type: 'Textbook', desc: 'Clear timelines, maps and summary tables for the O-Level history syllabus.' },
    { id: 'eng-uce-composition', title: 'UCE English Language: Composition and Comprehension', subject: 'English', level: 'o', price: 21000, pages: 168, edition: '2025 edition', type: 'Workbook', desc: 'Model essays, letter formats and comprehension drills with marking criteria.' },
    { id: 'lit-uace-set-texts', title: 'UACE Literature in English Study Companion', subject: 'Literature', level: 'a', price: 32000, pages: 210, edition: '2025 edition', type: 'Study guide', desc: 'Themes, character studies and essay plans for set texts in plays, novels and poetry.' },
    { id: 'art-uce-practical', title: 'UCE Art and Design Making Practical Guide', subject: 'Art & Design', level: 'o', price: 22000, pages: 112, edition: '2026 edition', type: 'Practical guide', desc: 'Planning sheets, material lists and step-by-step project examples for the practical paper.' },
    { id: 'ent-uce', title: 'UCE Entrepreneurship Education Made Simple', subject: 'Entrepreneurship', level: 'o', price: 19000, pages: 144, edition: '2025 edition', type: 'Textbook', desc: 'Business ideas, record keeping and simple plans, with real examples from Ugandan small businesses.' },
    { id: 'p7-math', title: 'Primary Seven Mathematics Revision', subject: 'Mathematics', level: 'primary', price: 12000, badge: 'PLE', pages: 120, edition: '2026 edition', type: 'Revision book', desc: 'Ten topic tests and four full mock papers with marking guides for Primary Seven candidates.' },
    { id: 'p7-science', title: 'Primary Seven Integrated Science Revision', subject: 'Science', level: 'primary', price: 12000, badge: 'PLE', pages: 112, edition: '2026 edition', type: 'Revision book', desc: 'Short notes, diagrams and PLE-style questions across all science topics.' },
    { id: 'p7-sst', title: 'Primary Seven Social Studies Revision', subject: 'Social Studies', level: 'primary', price: 11000, badge: 'PLE', pages: 104, edition: '2026 edition', type: 'Revision book', desc: 'Maps, summaries and past-paper style questions for Social Studies and Religious Education.' },
    { id: 'uace-bio-notes', title: 'UACE Biology Complete Notes', subject: 'Biology', level: 'a', price: 42000, pages: 320, edition: '2025 edition', type: 'Textbook', desc: 'Syllabus-matched notes with labelled diagrams, summary boxes and end-of-chapter questions.' },
    { id: 'uace-chem-notes', title: 'UACE Chemistry Complete Notes', subject: 'Chemistry', level: 'a', price: 42000, pages: 330, edition: '2025 edition', type: 'Textbook', desc: 'Physical, organic and inorganic chemistry explained with worked calculations.' },
    { id: 'uace-phy-notes', title: 'UACE Physics Complete Notes', subject: 'Physics', level: 'a', price: 42000, pages: 340, edition: '2025 edition', type: 'Textbook', desc: 'Concepts, derivations and solved problems for the full A-Level physics syllabus.' }
  ];

  /* ---------------- PAST PAPERS (generated) ---------------- */
  const PAPER_IMG = (window.WK ? WK.u('assets/img') : 'assets/img') + '/papers/';
  const realPaper = {
    'uce|Art & Design|Paper 2|2026': 'art-design-2026.jpg',
    'uce|Mathematics|Paper 1|2026': 'maths-p1-2026.jpg',
    'ple|Mathematics|Paper 1|2025': 'ple-maths-2025.jpg',
    'uace|Literature in English|Paper 2|2024': 'lit-english-2024.jpg'
  };
  const paperDefs = [
    { level: 'ple', years: [2022, 2023, 2024, 2025], subjects: { 'English': ['Paper 1'], 'Mathematics': ['Paper 1'], 'Science': ['Paper 1'], 'Social Studies': ['Paper 1'] }, print: 3000, pdf: 2000, time: '2 hours 30 minutes' },
    { level: 'uce', years: [2023, 2024, 2025, 2026], subjects: { 'Mathematics': ['Paper 1', 'Paper 2'], 'Biology': ['Paper 1', 'Paper 2'], 'Chemistry': ['Paper 1', 'Paper 2'], 'Physics': ['Paper 1', 'Paper 2'], 'Agriculture': ['Paper 1', 'Paper 2'], 'Geography': ['Paper 1', 'Paper 2'], 'History': ['Paper 1', 'Paper 2'], 'English': ['Paper 1', 'Paper 2'], 'Entrepreneurship': ['Paper 1'] }, print: 4000, pdf: 2500, time: '2 hours 15 minutes' },
    { level: 'uce', years: [2024, 2025, 2026], subjects: { 'Art & Design': ['Paper 2'] }, print: 4000, pdf: 2500, time: '4 hours' },
    { level: 'uace', years: [2023, 2024, 2025], subjects: { 'Biology': ['Paper 1', 'Paper 2'], 'Chemistry': ['Paper 1', 'Paper 2'], 'Physics': ['Paper 1', 'Paper 2'], 'Mathematics': ['Paper 1', 'Paper 2'], 'Agriculture': ['Paper 1', 'Paper 2'], 'Literature in English': ['Paper 2'], 'Economics': ['Paper 1', 'Paper 2'], 'General Paper': ['Paper 1'] }, print: 5000, pdf: 3000, time: '3 hours' }
  ];
  const papers = [];
  paperDefs.forEach(d => d.years.forEach(y => Object.keys(d.subjects).forEach(s => d.subjects[s].forEach(p => {
    const key = [d.level, s, p, y].join('|');
    papers.push({
      id: 'pp-' + key.toLowerCase().replace(/[^a-z0-9]+/g, '-'),
      level: d.level, subject: s, paper: p, year: y, time: d.time,
      print: d.print, pdf: d.pdf, img: realPaper[key] ? PAPER_IMG + realPaper[key] : null,
      title: (d.level === 'ple' ? 'PLE ' : '') + s + (d.level === 'ple' ? '' : ' ' + p)
    });
  }))));
  papers.sort((a, b) => b.year - a.year || a.subject.localeCompare(b.subject));

  /* ---------------- EXAM SETS ---------------- */
  const sets = [
    { id: 'set-uce-2026', level: 'uce', title: 'UCE Mock Examinations 2026: Full Set', year: 2026, price: 120000, worth: 148000, papers: 18, subjects: ['Mathematics', 'Biology', 'Chemistry', 'Physics', 'Agriculture', 'Geography', 'History', 'English', 'Entrepreneurship'], preview: ['maths-p1-2026.jpg', 'art-design-2026.jpg', 'maths-p1-2026-b.jpg'], blurb: 'Every UCE mock paper from the 2026 series with marking guides, ready to print for a whole class.' },
    { id: 'set-uace-2025', level: 'uace', title: 'UACE Mock Examinations 2025: Full Set', year: 2025, price: 135000, worth: 160000, papers: 16, subjects: ['Biology', 'Chemistry', 'Physics', 'Mathematics', 'Agriculture', 'Literature in English', 'Economics', 'General Paper'], preview: ['lit-english-2024.jpg', 'maths-p1-2026.jpg', 'art-design-2026.jpg'], blurb: 'The complete A-Level mock series with examiners reports and marking schemes.' },
    { id: 'set-ple-2025', level: 'ple', title: 'PLE Mock Examinations 2025: Full Set', year: 2025, price: 35000, worth: 44000, papers: 4, subjects: ['English', 'Mathematics', 'Science', 'Social Studies'], preview: ['ple-maths-2025.jpg', 'maths-p1-2026.jpg', 'maths-p1-2026-b.jpg'], blurb: 'All four PLE subjects set to the national format, with answer keys.' },
    { id: 'set-uce-maths-5y', level: 'uce', title: 'UCE Mathematics: Five-Year Paper Bundle', year: '2022 to 2026', price: 18000, worth: 25000, papers: 10, subjects: ['Paper 1 (2022 to 2026)', 'Paper 2 (2022 to 2026)'], preview: ['maths-p1-2026.jpg', 'maths-p1-2026-b.jpg', 'ple-maths-2025.jpg'], blurb: 'Ten papers in one bundle. The fastest way to practise one subject under exam conditions.' },
    { id: 'set-uce-sciences-2026', level: 'uce', title: 'UCE Sciences Pack 2026', year: 2026, price: 48000, worth: 60000, papers: 8, subjects: ['Biology P1 and P2', 'Chemistry P1 and P2', 'Physics P1 and P2', 'Mathematics P1 and P2'], preview: ['maths-p1-2026.jpg', 'maths-p1-2026-b.jpg', 'art-design-2026.jpg'], blurb: 'The four science and mathematics subjects together, for candidates in the science combination.' },
    { id: 'set-uace-arts-2024', level: 'uace', title: 'UACE Humanities Pack 2024', year: 2024, price: 40000, worth: 52000, papers: 7, subjects: ['Literature in English', 'Economics', 'General Paper'], preview: ['lit-english-2024.jpg', 'art-design-2026.jpg', 'maths-p1-2026.jpg'], blurb: 'Literature, Economics and General Paper with essay-writing guidance.' }
  ];

  /* ---------------- HOLIDAY PACKAGES ---------------- */
  const packages = [
    { id: 'hp-p4-p6', level: 'ple', name: 'Upper Primary Holiday Pack', for: 'Primary 4 to Primary 6', price: 45000, includes: ['Mathematics and English workbooks', 'Integrated Science activity book', 'Weekly revision tests with answers', 'Reading list and reading log', 'Learning calendar for the whole holiday'] },
    { id: 'hp-p7', level: 'ple', name: 'Primary Seven PLE Booster', for: 'Primary 7 candidates', price: 65000, best: true, includes: ['Four PLE subject revision books', 'PLE Mock Set with answer keys', 'Topic tests for all subjects', 'Examination technique sheet', 'Parent progress tracker'] },
    { id: 'hp-s1-s2', level: 'uce', name: 'Junior Secondary Holiday Pack', for: 'Senior 1 and Senior 2', price: 70000, includes: ['New curriculum practical workbooks', 'Mathematics revision questions', 'English composition guide', 'Subject quizzes for each term', 'Holiday project ideas'] },
    { id: 'hp-s3', level: 'uce', name: 'Senior Three Foundation Pack', for: 'Senior 3', price: 80000, includes: ['Biology, Chemistry and Physics practical workbooks', 'Mathematics revision questions', 'Past papers from the previous two years', 'Revision timetable', 'Study skills guide'] },
    { id: 'hp-s4', level: 'uce', name: 'UCE Candidate Holiday Pack', for: 'Senior 4 candidates', price: 110000, best: true, includes: ['Subject practical workbooks of your choice', 'UCE Mock Set 2026', 'Five-year Mathematics bundle', 'Marking guides for every paper', 'Candidate revision planner'] },
    { id: 'hp-s6', level: 'uace', name: 'UACE Candidate Holiday Pack', for: 'Senior 6 candidates', price: 130000, includes: ['Complete notes for your subjects', 'UACE Mock Set 2025', 'Past papers from three years', 'Examiners tips for each paper', 'Essay and practical guidance'] }
  ];

  /* ---------------- helpers ---------------- */
  const money = n => 'UGX ' + Number(n).toLocaleString('en-UG');
  const byId = (arr, id) => arr.find(x => x.id === id);

  return { LEVELS, BOOK_LEVELS, SUBJECTS, books, papers, sets, packages, money, byId, PAPER_IMG };
})();
