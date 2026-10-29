// ALL visible text of the site lives here, in two languages.
//   en = English, bn = বাংলা (Bangla)
// To add or change a sentence, edit it here - no other file needs to change.
// Components read the text with:  const { t } = useLanguage()   ->   t.nav.home

const translations = {
  en: {
    nav: {
      home: 'Home',
      exploreLaws: 'Explore Laws',
      aiAssistant: 'AI Legal Assistant',
      findLawyer: 'Find a Lawyer',
      about: 'About',
      login: 'Login',
      getStarted: 'Get Started',
      tagline: 'BANGLADESH LEGAL GUIDE',
      menu: 'Toggle menu',
      languageLabel: 'Language',
    },

    hero: {
      badge: 'Legal information for Bangladesh · Demo Prototype',
      // The headline is split into lines so we can control the line breaks.
      line1: 'Understand',
      line2: 'Your Rights.',
      line3: 'Navigate the Law',
      line4Start: 'with ',
      line4Highlight: 'Confidence.', // shown in gold italic
      line4End: '',
      description:
        "LawAssist AI helps you understand Bangladesh's land, family, labour, business and consumer laws in Bangla or English — with the source of every answer shown, so you can check it yourself.",
      askButton: 'Ask a Legal Question',
      exploreButton: 'Explore Bangladesh Laws',
    },

    // The four short promises shown in the strip under the hero
    features: [
      { title: 'Bangla & English', text: 'Ask and read in the language you are most comfortable with.' },
      { title: 'Sources shown', text: 'Every answer points to the law it draws on, so you can verify it.' },
      { title: 'Plain language', text: 'Legal terms explained simply, without the jargon.' },
      { title: 'Information, not advice', text: 'A demo prototype for learning. It does not replace a lawyer.' },
    ],

    // The "Areas of Law" section: five cards. "id" is used in the link (/laws?area=land).
    // In English each card shows a Bangla subtitle; in Bangla the subtitle is empty (no English words).
    areas: {
      eyebrow: 'AREAS OF LAW',
      headingLine1: 'Start with the part of', // first line of the heading
      headingHighlight: 'life', // shown in gold italic
      headingEnd: ' the law touches',
      description: 'Choose an area to see common questions, plain-language explanations and the laws behind them.',
      tagline: ['Know', 'Understand', 'Take the right step'],
      explore: 'Explore',
      cards: [
        { id: 'land', title: 'Land & Property', subtitle: 'ভূমি ও সম্পত্তি', text: 'Check ownership, understand deeds and mutation, and know your rights before you buy or sell.' },
        { id: 'family', title: 'Family Law', subtitle: 'পারিবারিক আইন', text: 'Marriage, divorce, maintenance, custody and inheritance, explained step by step.' },
        { id: 'labour', title: 'Labour & Employment', subtitle: 'শ্রম ও কর্মসংস্থান', text: 'Wages, contracts, working hours and leave — and what to do when pay is withheld.' },
        { id: 'business', title: 'Business', subtitle: 'ব্যবসা', text: 'Registering a company, contracts, licences and the rules that apply to running a business.' },
        { id: 'consumer', title: 'Consumer Rights', subtitle: 'ভোক্তা অধিকার', text: 'Faulty goods, misleading sellers and where to take a complaint.' },
      ],
    },

    // The "How it works" section: three steps
    howItWorks: {
      eyebrow: 'HOW IT WORKS',
      line1: 'From a confusing situation',
      line2: 'to a clear next step',
      steps: [
        { number: '01', title: 'Ask in your own words', text: 'Type your question in Bangla or English. No legal vocabulary needed.' },
        { number: '02', title: 'Read a plain-language answer', text: 'Get a structured explanation of your rights and the usual next steps.' },
        { number: '03', title: 'Check the source', text: 'Open the law an answer relies on, and find a lawyer when you need one.' },
      ],
    },

    // The "Find a Lawyer" section: intro, filter chips and three DEMO profiles (fictional people).
    lawyers: {
      eyebrow: 'FIND A LAWYER',
      line1: 'When you need a person,',
      line2: 'not just information',
      description:
        'Browse advocates by practice area, language and court. Profiles shown here are illustrative samples for the prototype.',
      filters: {
        area: {
          label: 'Practice area',
          any: 'Any practice area',
          options: { land: 'Land & Property', family: 'Family Law', labour: 'Labour & Employment' },
        },
        language: { label: 'Language', any: 'Any language', options: { bn: 'Bangla', en: 'English' } },
        court: {
          label: 'Court / jurisdiction',
          any: 'Any court',
          options: { supreme: 'Supreme Court of Bangladesh', dhaka: 'Dhaka District Courts', labour: 'Dhaka Labour Court' },
        },
        availability: {
          label: 'Availability',
          any: 'Any format',
          options: { person: 'In person', video: 'Video call' },
        },
        fee: {
          label: 'Consultation fee',
          any: 'Any fee',
          options: { 1000: 'Up to BDT 1,000', 1200: 'Up to BDT 1,200', 1500: 'Up to BDT 1,500' },
        },
      },
      showing: 'Showing {shown} of {total} demo profiles',
      clear: 'Clear filters',
      empty: 'No demo profile matches these filters. Try clearing one of them.',
      cta: 'Find a Lawyer',
      trust: [
        { title: 'Verified advocates', text: 'Planned for launch' },
        { title: 'Major courts of Bangladesh', text: 'Planned for launch' },
        { title: 'Multiple practice areas', text: 'Land, family, labour and more' },
      ],
      demoLabel: 'Demo profile',
      perConsultation: 'per consultation',
      illustrativeFee: 'illustrative fee',
      viewProfile: 'View Profile',
      bothFormats: 'In person or video',
      listLabel: 'Demo advocate profiles',
      profiles: [
        { id: 'tanvir', name: 'Md. Tanvir Rahman', fee: 'BDT 1,500' },
        { id: 'nusrat', name: 'Nusrat Jahan', fee: 'BDT 1,200' },
        { id: 'sabbir', name: 'Sabbir Ahmed', fee: 'BDT 1,000' },
      ],
    },

    // The closing banner at the bottom of the home page
    finalCta: {
      headingStart: 'Understand your rights ',
      headingHighlight: 'before', // shown in gold italic
      headingEnd: ' you act',
      text: 'Ask your first question in Bangla or English. It takes less than a minute.',
    },

    // The footer (the link names themselves come from "nav" above)
    footer: {
      description:
        'A Bangladeshi legal guide that explains the law in plain Bangla and English, and shows where every answer comes from.',
      languages: 'Bangla · English',
      explore: 'EXPLORE',
      brand: 'LAWASSIST AI',
      language: 'LANGUAGE',
      copyright: '© 2026 LawAssist AI · Demo prototype',
    },

    // ---------- /register, /login and /dashboard ----------
    auth: {
      demoLabel: 'Demo mode',
      show: 'Show',
      hide: 'Hide',
      fields: {
        name: { label: 'Full name', placeholder: 'Your full name' },
        email: { label: 'Email address', placeholder: 'name@example.com' },
        password: { label: 'Password', placeholder: 'Create a password' },
        confirm: { label: 'Confirm password', placeholder: 'Type the password again' },
        loginPassword: { label: 'Password', placeholder: 'Your password' },
      },
      passwordHint: 'At least 8 characters, with a letter and a number.',
      showPassword: 'Show password',
      hidePassword: 'Hide password',
      errors: {
        fixThese: 'Please fix the highlighted fields.',
        nameRequired: 'Please enter your full name.',
        nameShort: 'Your name should have at least 2 characters.',
        emailRequired: 'Please enter your email address.',
        emailInvalid: 'Please enter a valid email address, like name@example.com.',
        passwordRequired: 'Please enter a password.',
        passwordShort: 'Your password needs at least 8 characters.',
        passwordWeak: 'Add at least one letter and one number.',
        confirmRequired: 'Please type your password again.',
        confirmMismatch: 'The two passwords do not match.',
        termsRequired: 'Please agree to the Terms and Privacy Policy to continue.',
        emailTaken: 'An account with this email already exists. Try logging in instead.',
        invalidCredentials: 'The email or password is not correct.',
        registerFailed: 'We could not create your account. Please try again.',
        loginFailed: 'We could not log you in. Please try again.',
      },
      register: {
        eyebrow: 'GET STARTED',
        title: 'Create your account',
        intro: 'Create an account to get started with LawAssist AI.',
        sideTitle: 'Start with a clear question',
        sideText: 'LawAssist AI is being built to explain Bangladeshi law in plain Bangla and English. This prototype lets you look around.',
        sideListTitle: 'What you can try today',
        sideList: ['Explore areas of law', 'Try the assistant chat screen', 'Browse demo lawyer profiles'],
        terms: 'I agree to the Terms of Use and Privacy Policy',
        termsNote: 'These documents are not published yet in this demo prototype.',
        submit: 'Create Account',
        submitting: 'Creating account…',
        haveAccount: 'Already have an account?',
        loginLink: 'Log in',
        demoText:
          'This prototype has no sign-up service yet. Your details are only checked on this page. No account is created, and nothing is saved or sent anywhere.',
        resultTitle: 'Details checked — no account was created',
        resultText: 'Because the prototype has no sign-up service, we did not create an account, send your details anywhere, or store your password.',
        preview: 'Preview the welcome dashboard',
        edit: 'Go back and edit',
      },
      login: {
        eyebrow: 'LAWASSIST AI',
        line1: 'Your Legal Clarity',
        line2: 'Starts Here',
        description:
          'Understand Bangladesh law in plain Bangla and English. Ask a question, read a clear explanation, and know where to look next.',
        points: ['Plain Bangla and English', 'Educational information, never legal advice', 'Made for people without legal training'],
        welcome: 'Welcome back',
        subtitle: 'Sign in to continue to LawAssist AI.',
        demoPill: 'Demo prototype · real sign-in comes later',
        remember: 'Remember me',
        forgot: 'Forgot password?',
        forgotInfo: 'Password reset is not available in this prototype.',
        submit: 'Sign In',
        submitting: 'Signing in…',
        or: 'OR',
        google: 'Continue with Google',
        noAccount: 'New to LawAssist AI?',
        registerLink: 'Create an account',
        notConnectedTitle: 'Sign-in is not connected yet',
        notConnectedText:
          'Your details were checked on this page only. Nothing was sent or saved, and you are not signed in. You can still look around in demo mode.',
        demoAction: 'Continue in Demo Mode',
        googleDialog: {
          title: 'Google sign-in is coming later',
          text: 'Real Google authentication has not been connected yet. You can continue in demo mode to try the AI Legal Assistant. You will not be signed in with Google, and no account will be created.',
          badge: 'Demo only',
          continue: 'Continue in Demo Mode',
          cancel: 'Cancel',
        },
      },
      dashboard: {
        eyebrow: 'DASHBOARD',
        welcome: 'Welcome',
        welcomeName: 'Welcome, {name}',
        intro: 'Where would you like to start?',
        previewTitle: 'Preview only',
        previewText: 'You are not signed in. Accounts and sign-in are not available in this prototype, so this page shows the same options to everyone.',
        actions: [
          { title: 'Ask a Legal Question', text: 'Open the assistant chat screen.' },
          { title: 'Explore Areas of Law', text: 'Browse land, family, labour and more.' },
          { title: 'Find a Lawyer', text: 'Search the demo lawyer directory.' },
        ],
      },
    },

    question: {
      placeholder: 'e.g. How do I check land ownership before buying?',
      send: 'Send question',
      tryExample: 'Try an example',
      examples: [
        'What can I do about a land deed dispute?',
        'What are my rights if my employer withholds my salary?',
        'Where do I file a consumer rights complaint?',
      ],
      disclaimer:
        'Educational information only — not legal advice. No lawyer-client relationship is created.',
      emptyError: 'Please type your question first.',
    },

    // ---------- /assistant page ----------
    assistant: {
      title: 'AI Legal Assistant',
      welcomeTitle: 'How can I help you understand the law?',
      welcomeSubtitle: 'Get general legal information related to Bangladesh, in Bangla or English.',
      topicsLabel: 'Choose a topic to start',
      topics: [
        { title: 'Land & Property Law', text: 'Ownership, deeds and disputes', question: 'How do I check who legally owns a piece of land before I buy it?' },
        { title: 'Family Law', text: 'Marriage, inheritance and family disputes', question: 'What are my rights in a family property or inheritance dispute?' },
        { title: 'Labour Law', text: 'Salary, contracts and workplace rights', question: 'My employer has not paid my salary on time. What can I do?' },
        { title: 'Business & Consumer Rights', text: 'Complaints, shops and services', question: 'Where can I file a complaint if a shop sold me a faulty product?' },
      ],
      sidebarLabel: 'Chat sidebar',
      newChat: 'New Chat',
      searchLabel: 'Search conversations',
      searchPlaceholder: 'Search conversations',
      recent: 'Recent chats',
      sampleTag: 'Sample',
      sampleNote: 'These are sample items for the demo. Chat history is not saved yet.',
      noMatch: 'No conversations match your search.',
      samples: [
        { title: 'Checking land ownership', question: 'How do I check who owns a piece of land before I buy it?' },
        { title: 'Unpaid salary: what can I do?', question: 'My employer has not paid my salary. What can I do?' },
        { title: 'Consumer complaint steps', question: 'Where do I file a complaint about a faulty product?' },
        { title: 'Rights in a family dispute', question: 'What are my rights in a family property dispute?' },
      ],
      guestName: 'Demo visitor',
      guestStatus: 'Not signed in',
      loginLink: 'Log in',
      signedInStatus: 'Signed in',
      openMenu: 'Open chat sidebar',
      closeMenu: 'Close chat sidebar',
      demoPill: 'Demo · AI not connected',
      conversation: 'Conversation',
      placeholder: 'Ask a legal question in Bangla or English…',
      send: 'Send',
      sending: 'Looking into your question…',
      you: 'You',
      assistantName: 'LawAssist AI',
      placeholderLabel: 'Placeholder · not an answer',
      notConnectedReply: 'AI responses will be connected soon.',
      errorReply: 'Something went wrong while getting an answer. Please try again.',
      retry: 'Try again',
      sources: 'Sources',
      hint: 'Enter to send · Shift + Enter for a new line',
      disclaimer: 'This AI assistant provides general legal information, not professional legal advice.',
    },

    // ---------- /lawyers page (the profile data and filter names come from "lawyers" above) ----------
    lawyersPage: {
      eyebrow: 'FIND A LAWYER',
      title: 'Lawyer directory',
      description:
        'Search sample advocate profiles by practice area, language, court and consultation fee, to see how the directory will work.',
      demoTitle: 'Demo profiles only',
      demoText:
        'The advocates listed here are fictional samples. They are not real people, they are not verified, and they cannot be contacted or booked. Fees are illustrative.',
      searchLabel: 'Search',
      searchPlaceholder: 'Search name, area or court',
      filtersLabel: 'Filters',
      backToAll: 'Back to all profiles',
      detailsLabel: 'Profile details',
      area: 'Practice area',
      court: 'Court / jurisdiction',
      languages: 'Languages',
      format: 'Consultation format',
      fee: 'Illustrative fee',
      aboutTitle: 'About this profile',
      aboutText: 'This is a fictional sample profile, created only to show how the directory will look. It is not a real advocate.',
      requestConsultation: 'Request a consultation',
      unavailable: 'Booking is not available in the prototype.',
      notFoundTitle: 'Profile not found',
      notFoundText: 'There is no demo profile with that address.',
    },

    // ---------- /about page ----------
    about: {
      eyebrow: 'ABOUT LAWASSIST AI',
      line1: 'Legal information,',
      line2: 'in language you can follow',
      lead: 'LawAssist AI is a Bangladeshi legal-information platform. It is being designed to explain the law in plain Bangla and English, and to show where each explanation comes from.',
      missionTitle: 'Why it exists',
      missionText:
        'Many people in Bangladesh meet a land, family, workplace or consumer problem without knowing where to start. Legal language, and the gap between Bangla and English sources, makes it harder. LawAssist AI aims to make legal information easier to understand in both languages, so people can ask better questions before they act.',
      howTitle: 'How it helps',
      how: [
        { title: 'Explore legal topics', text: 'Browse areas of law such as land, family and labour, with common questions and plain-language explanations.' },
        { title: 'Understand answers', text: 'Ask in Bangla or English and get an explanation without legal jargon.' },
        { title: 'Find the source', text: 'Each answer is meant to point to the law or provision it is based on, so you can read it yourself.' },
      ],
      audienceTitle: 'Who it is for',
      audience: [
        { title: 'People without legal training', text: 'Anyone in Bangladesh who wants to understand a legal question before deciding what to do.' },
        { title: 'Students and learners', text: 'Learners who want plain-language explanations next to the source law.' },
        { title: 'Community helpers', text: 'People who support others with everyday legal questions and want a clear starting point.' },
      ],
      featuresTitle: 'Key features',
      features: [
        { title: 'Bangla and English', text: 'Switch the whole site between বাংলা and English at any time.' },
        { title: 'Areas of law', text: 'A starting point for common topics such as land, family and labour.' },
        { title: 'AI assistant', text: 'A chat screen for legal questions. The AI itself is not connected yet.' },
        { title: 'Lawyer directory', text: 'Search and filter sample advocate profiles. All profiles are fictional demos.' },
      ],
      honestTitle: 'Please read this',
      honestText:
        'LawAssist AI is a demo prototype. It gives educational information only, is not legal advice, and does not replace a qualified lawyer. Using it does not create a lawyer-client relationship. For a real legal problem, speak to a licensed advocate.',
      statusTitle: 'Where the prototype stands',
      workingLabel: 'Working now',
      working: ['Bangla and English interface', 'Areas of law overview', 'Lawyer directory with working filters (demo profiles)', 'Assistant chat screen'],
      plannedLabel: 'Not built yet',
      planned: ['AI-generated answers (no backend connected)', 'Source citations for answers', 'Verified advocate listings', 'Accounts and sign-in'],
      ctaTitle: 'Start exploring',
      ctaText: 'Browse the areas of law, or try the assistant chat screen.',
    },

  },

  bn: {
    nav: {
      home: 'হোম',
      exploreLaws: 'আইন অনুসন্ধান',
      aiAssistant: 'এআই আইনি সহকারী',
      findLawyer: 'আইনজীবী খুঁজুন',
      about: 'আমাদের সম্পর্কে',
      login: 'লগইন',
      getStarted: 'শুরু করুন',
      tagline: 'বাংলাদেশ আইনি নির্দেশিকা',
      menu: 'মেনু খুলুন বা বন্ধ করুন',
      languageLabel: 'ভাষা',
    },

    hero: {
      badge: 'বাংলাদেশের জন্য আইনি তথ্য · ডেমো প্রোটোটাইপ',
      line1: 'আপনার অধিকার',
      line2: 'জানুন।',
      line3: 'আইনের পথে চলুন',
      line4Start: '',
      line4Highlight: 'আত্মবিশ্বাসের',
      line4End: ' সাথে।',
      description:
        'LawAssist AI বাংলা বা ইংরেজিতে বাংলাদেশের ভূমি, পারিবারিক, শ্রম, ব্যবসা ও ভোক্তা আইন বুঝতে আপনাকে সাহায্য করে — প্রতিটি উত্তরের উৎস দেখানো হয়, যাতে আপনি নিজেই যাচাই করে নিতে পারেন।',
      askButton: 'আইনি প্রশ্ন করুন',
      exploreButton: 'বাংলাদেশের আইন দেখুন',
    },

    features: [
      { title: 'বাংলা ও ইংরেজি', text: 'আপনার সবচেয়ে স্বচ্ছন্দ ভাষায় প্রশ্ন করুন ও উত্তর পড়ুন।' },
      { title: 'উৎস দেখানো হয়', text: 'প্রতিটি উত্তর কোন আইনের ওপর ভিত্তি করে, তা দেখানো হয়, যাতে আপনি যাচাই করতে পারেন।' },
      { title: 'সহজ ভাষা', text: 'আইনি পরিভাষা জটিল শব্দ ছাড়াই সহজভাবে ব্যাখ্যা করা হয়।' },
      { title: 'তথ্য, পরামর্শ নয়', text: 'এটি শেখার জন্য একটি ডেমো প্রোটোটাইপ। এটি আইনজীবীর বিকল্প নয়।' },
    ],

    areas: {
      eyebrow: 'আইনের ক্ষেত্র',
      headingLine1: 'জীবনের যে অংশটি আইন স্পর্শ করে,',
      headingHighlight: 'সেখান থেকেই',
      headingEnd: ' শুরু করুন',
      description: 'একটি ক্ষেত্র বেছে নিন — সাধারণ প্রশ্ন, সহজ ভাষার ব্যাখ্যা এবং এর পেছনের আইনগুলো দেখুন।',
      tagline: ['জানুন', 'বুঝুন', 'সঠিক পদক্ষেপ নিন'],
      explore: 'দেখুন',
      cards: [
        { id: 'land', title: 'ভূমি ও সম্পত্তি', subtitle: '', text: 'মালিকানা যাচাই করুন, দলিল ও নামজারি বুঝুন, এবং কেনাবেচার আগে নিজের অধিকার জানুন।' },
        { id: 'family', title: 'পারিবারিক আইন', subtitle: '', text: 'বিবাহ, বিবাহবিচ্ছেদ, ভরণপোষণ, অভিভাবকত্ব ও উত্তরাধিকার — ধাপে ধাপে ব্যাখ্যাসহ।' },
        { id: 'labour', title: 'শ্রম ও কর্মসংস্থান', subtitle: '', text: 'মজুরি, চুক্তি, কাজের সময় ও ছুটি — আর বেতন আটকে রাখলে কী করবেন।' },
        { id: 'business', title: 'ব্যবসা', subtitle: '', text: 'কোম্পানি নিবন্ধন, চুক্তি, লাইসেন্স এবং ব্যবসা পরিচালনার নিয়মকানুন।' },
        { id: 'consumer', title: 'ভোক্তা অধিকার', subtitle: '', text: 'ত্রুটিপূর্ণ পণ্য, বিভ্রান্তিকর বিক্রেতা এবং অভিযোগ কোথায় করবেন।' },
      ],
    },

    howItWorks: {
      eyebrow: 'কীভাবে কাজ করে',
      line1: 'একটি বিভ্রান্তিকর পরিস্থিতি থেকে',
      line2: 'একটি স্পষ্ট পরবর্তী পদক্ষেপে',
      steps: [
        { number: '০১', title: 'নিজের ভাষায় জিজ্ঞাসা করুন', text: 'বাংলা বা ইংরেজিতে আপনার প্রশ্ন লিখুন। আইনি পরিভাষা জানার প্রয়োজন নেই।' },
        { number: '০২', title: 'সহজ ভাষায় উত্তর পড়ুন', text: 'আপনার অধিকার ও সাধারণ পরবর্তী ধাপগুলোর একটি গোছানো ব্যাখ্যা পান।' },
        { number: '০৩', title: 'উৎস যাচাই করুন', text: 'উত্তর যে আইনের ওপর নির্ভর করে তা খুলে দেখুন, আর প্রয়োজনে একজন আইনজীবী খুঁজুন।' },
      ],
    },

    lawyers: {
      eyebrow: 'আইনজীবী খুঁজুন',
      line1: 'যখন প্রয়োজন একজন মানুষ,',
      line2: 'শুধু তথ্য নয়',
      description:
        'অনুশীলনের ক্ষেত্র, ভাষা ও আদালত অনুযায়ী আইনজীবী খুঁজুন। এখানে দেখানো প্রোফাইলগুলো প্রোটোটাইপের জন্য উদাহরণমাত্র।',
      filters: {
        area: {
          label: 'অনুশীলনের ক্ষেত্র',
          any: 'যেকোনো ক্ষেত্র',
          options: { land: 'ভূমি ও সম্পত্তি', family: 'পারিবারিক আইন', labour: 'শ্রম ও কর্মসংস্থান' },
        },
        language: { label: 'ভাষা', any: 'যেকোনো ভাষা', options: { bn: 'বাংলা', en: 'ইংরেজি' } },
        court: {
          label: 'আদালত / এখতিয়ার',
          any: 'যেকোনো আদালত',
          options: { supreme: 'বাংলাদেশ সুপ্রিম কোর্ট', dhaka: 'ঢাকা জেলা আদালত', labour: 'ঢাকা শ্রম আদালত' },
        },
        availability: {
          label: 'সহজলভ্যতা',
          any: 'যেকোনো মাধ্যম',
          options: { person: 'সরাসরি', video: 'ভিডিও কল' },
        },
        fee: {
          label: 'পরামর্শ ফি',
          any: 'যেকোনো ফি',
          options: { 1000: '৳ ১,০০০ পর্যন্ত', 1200: '৳ ১,২০০ পর্যন্ত', 1500: '৳ ১,৫০০ পর্যন্ত' },
        },
      },
      showing: '{total}টির মধ্যে {shown}টি ডেমো প্রোফাইল দেখানো হচ্ছে',
      clear: 'ফিল্টার মুছুন',
      empty: 'এই ফিল্টারগুলোর সাথে কোনো ডেমো প্রোফাইল মেলেনি। একটি ফিল্টার মুছে দেখুন।',
      cta: 'আইনজীবী খুঁজুন',
      trust: [
        { title: 'যাচাইকৃত আইনজীবী', text: 'চালুর সময় থাকার পরিকল্পনা' },
        { title: 'বাংলাদেশের প্রধান আদালতসমূহ', text: 'চালুর সময় থাকার পরিকল্পনা' },
        { title: 'একাধিক অনুশীলন ক্ষেত্র', text: 'ভূমি, পারিবারিক, শ্রম ও আরও' },
      ],
      demoLabel: 'ডেমো প্রোফাইল',
      perConsultation: 'প্রতি পরামর্শে',
      illustrativeFee: 'উদাহরণ ফি',
      viewProfile: 'প্রোফাইল দেখুন',
      bothFormats: 'সরাসরি বা ভিডিও',
      listLabel: 'ডেমো আইনজীবী প্রোফাইল',
      profiles: [
        { id: 'tanvir', name: 'মো. তানভীর রহমান', fee: '৳ ১,৫০০' },
        { id: 'nusrat', name: 'নুসরাত জাহান', fee: '৳ ১,২০০' },
        { id: 'sabbir', name: 'সাব্বির আহমেদ', fee: '৳ ১,০০০' },
      ],
    },

    finalCta: {
      headingStart: 'কাজ করার ',
      headingHighlight: 'আগেই',
      headingEnd: ' আপনার অধিকার বুঝুন',
      text: 'বাংলা বা ইংরেজিতে আপনার প্রথম প্রশ্নটি করুন। এতে এক মিনিটেরও কম সময় লাগে।',
    },

    footer: {
      description:
        'একটি বাংলাদেশি আইনি নির্দেশিকা, যা সহজ বাংলা ও ইংরেজিতে আইন ব্যাখ্যা করে এবং প্রতিটি উত্তরের উৎস দেখায়।',
      languages: 'বাংলা · ইংরেজি',
      explore: 'অন্বেষণ',
      brand: 'LawAssist AI',
      language: 'ভাষা',
      copyright: '© ২০২৬ LawAssist AI · ডেমো প্রোটোটাইপ',
    },

    // ---------- /register, /login ও /dashboard ----------
    auth: {
      demoLabel: 'ডেমো মোড',
      show: 'দেখান',
      hide: 'লুকান',
      fields: {
        name: { label: 'পুরো নাম', placeholder: 'আপনার পুরো নাম' },
        email: { label: 'ইমেইল ঠিকানা', placeholder: 'name@example.com' },
        password: { label: 'পাসওয়ার্ড', placeholder: 'একটি পাসওয়ার্ড তৈরি করুন' },
        confirm: { label: 'পাসওয়ার্ড নিশ্চিত করুন', placeholder: 'পাসওয়ার্ডটি আবার লিখুন' },
        loginPassword: { label: 'পাসওয়ার্ড', placeholder: 'আপনার পাসওয়ার্ড' },
      },
      passwordHint: 'কমপক্ষে ৮টি অক্ষর, এর মধ্যে একটি অক্ষর ও একটি সংখ্যা থাকতে হবে।',
      showPassword: 'পাসওয়ার্ড দেখান',
      hidePassword: 'পাসওয়ার্ড লুকান',
      errors: {
        fixThese: 'অনুগ্রহ করে চিহ্নিত ঘরগুলো ঠিক করুন।',
        nameRequired: 'অনুগ্রহ করে আপনার পুরো নাম লিখুন।',
        nameShort: 'নামে কমপক্ষে ২টি অক্ষর থাকতে হবে।',
        emailRequired: 'অনুগ্রহ করে আপনার ইমেইল ঠিকানা লিখুন।',
        emailInvalid: 'অনুগ্রহ করে সঠিক ইমেইল ঠিকানা লিখুন, যেমন name@example.com।',
        passwordRequired: 'অনুগ্রহ করে একটি পাসওয়ার্ড লিখুন।',
        passwordShort: 'পাসওয়ার্ডে কমপক্ষে ৮টি অক্ষর থাকতে হবে।',
        passwordWeak: 'কমপক্ষে একটি অক্ষর ও একটি সংখ্যা যোগ করুন।',
        confirmRequired: 'অনুগ্রহ করে পাসওয়ার্ডটি আবার লিখুন।',
        confirmMismatch: 'দুটি পাসওয়ার্ড মিলছে না।',
        termsRequired: 'এগিয়ে যেতে অনুগ্রহ করে শর্তাবলি ও গোপনীয়তা নীতিতে সম্মতি দিন।',
        emailTaken: 'এই ইমেইল দিয়ে আগেই একটি অ্যাকাউন্ট আছে। বরং লগইন করে দেখুন।',
        invalidCredentials: 'ইমেইল বা পাসওয়ার্ড সঠিক নয়।',
        registerFailed: 'আপনার অ্যাকাউন্ট তৈরি করা যায়নি। অনুগ্রহ করে আবার চেষ্টা করুন।',
        loginFailed: 'আপনাকে লগইন করানো যায়নি। অনুগ্রহ করে আবার চেষ্টা করুন।',
      },
      register: {
        eyebrow: 'শুরু করুন',
        title: 'আপনার অ্যাকাউন্ট তৈরি করুন',
        intro: 'LawAssist AI দিয়ে শুরু করতে একটি অ্যাকাউন্ট তৈরি করুন।',
        sideTitle: 'একটি স্পষ্ট প্রশ্ন দিয়ে শুরু করুন',
        sideText: 'LawAssist AI তৈরি হচ্ছে সহজ বাংলা ও ইংরেজিতে বাংলাদেশের আইন ব্যাখ্যা করার জন্য। এই প্রোটোটাইপে আপনি ঘুরে দেখতে পারেন।',
        sideListTitle: 'আজ যা চেষ্টা করতে পারেন',
        sideList: ['আইনের ক্ষেত্রগুলো দেখুন', 'সহকারীর চ্যাট পর্দা চেষ্টা করুন', 'ডেমো আইনজীবী প্রোফাইল দেখুন'],
        terms: 'আমি ব্যবহারের শর্তাবলি ও গোপনীয়তা নীতিতে সম্মত',
        termsNote: 'এই ডেমো প্রোটোটাইপে এই নথিগুলো এখনও প্রকাশিত হয়নি।',
        submit: 'অ্যাকাউন্ট তৈরি করুন',
        submitting: 'অ্যাকাউন্ট তৈরি হচ্ছে…',
        haveAccount: 'আগে থেকেই অ্যাকাউন্ট আছে?',
        loginLink: 'লগইন করুন',
        demoText:
          'এই প্রোটোটাইপে এখনও কোনো সাইন-আপ সেবা নেই। আপনার তথ্য শুধু এই পাতায় যাচাই করা হয়। কোনো অ্যাকাউন্ট তৈরি হয় না, কিছু সংরক্ষণ বা কোথাও পাঠানোও হয় না।',
        resultTitle: 'তথ্য যাচাই হয়েছে — কোনো অ্যাকাউন্ট তৈরি হয়নি',
        resultText: 'প্রোটোটাইপে সাইন-আপ সেবা না থাকায় আমরা অ্যাকাউন্ট তৈরি করিনি, আপনার তথ্য কোথাও পাঠাইনি বা পাসওয়ার্ড সংরক্ষণ করিনি।',
        preview: 'স্বাগত ড্যাশবোর্ডের নমুনা দেখুন',
        edit: 'ফিরে গিয়ে সম্পাদনা করুন',
      },
      login: {
        eyebrow: 'LawAssist AI',
        line1: 'আপনার আইনি স্বচ্ছতা',
        line2: 'শুরু হোক এখানে',
        description:
          'সহজ বাংলা ও ইংরেজিতে বাংলাদেশের আইন বুঝুন। প্রশ্ন করুন, পরিষ্কার ব্যাখ্যা পড়ুন, আর জানুন এরপর কোথায় দেখবেন।',
        points: ['সহজ বাংলা ও ইংরেজি', 'শিক্ষামূলক তথ্য, কখনোই আইনি পরামর্শ নয়', 'আইনের প্রশিক্ষণ নেই এমন মানুষের জন্য'],
        welcome: 'আবার স্বাগতম',
        subtitle: 'LawAssist AI-তে এগিয়ে যেতে সাইন ইন করুন।',
        demoPill: 'ডেমো প্রোটোটাইপ · আসল সাইন-ইন পরে আসবে',
        remember: 'আমাকে মনে রাখুন',
        forgot: 'পাসওয়ার্ড ভুলে গেছেন?',
        forgotInfo: 'এই প্রোটোটাইপে পাসওয়ার্ড রিসেট করা যায় না।',
        submit: 'সাইন ইন',
        submitting: 'সাইন ইন হচ্ছে…',
        or: 'অথবা',
        google: 'গুগল দিয়ে চালিয়ে যান',
        noAccount: 'LawAssist AI-তে নতুন?',
        registerLink: 'অ্যাকাউন্ট তৈরি করুন',
        notConnectedTitle: 'সাইন-ইন এখনও সংযুক্ত নয়',
        notConnectedText:
          'আপনার তথ্য শুধু এই পাতায় যাচাই করা হয়েছে। কিছু পাঠানো বা সংরক্ষণ করা হয়নি, এবং আপনি সাইন ইন করা নেই। চাইলে ডেমো মোডে ঘুরে দেখতে পারেন।',
        demoAction: 'ডেমো মোডে চালিয়ে যান',
        googleDialog: {
          title: 'গুগল সাইন-ইন পরে আসবে',
          text: 'আসল গুগল প্রমাণীকরণ এখনও সংযুক্ত হয়নি। এআই আইনি সহকারী চেষ্টা করতে আপনি ডেমো মোডে চালিয়ে যেতে পারেন। আপনি গুগল দিয়ে সাইন ইন হবেন না, এবং কোনো অ্যাকাউন্ট তৈরি হবে না।',
          badge: 'শুধুই ডেমো',
          continue: 'ডেমো মোডে চালিয়ে যান',
          cancel: 'বাতিল',
        },
      },
      dashboard: {
        eyebrow: 'ড্যাশবোর্ড',
        welcome: 'স্বাগতম',
        welcomeName: 'স্বাগতম, {name}',
        intro: 'আপনি কোথা থেকে শুরু করতে চান?',
        previewTitle: 'শুধু নমুনা',
        previewText: 'আপনি লগইন করা নেই। এই প্রোটোটাইপে অ্যাকাউন্ট ও লগইন নেই, তাই এই পাতায় সবার জন্য একই বিকল্প দেখানো হচ্ছে।',
        actions: [
          { title: 'আইনি প্রশ্ন করুন', text: 'সহকারীর চ্যাট পর্দা খুলুন।' },
          { title: 'আইনের ক্ষেত্র দেখুন', text: 'ভূমি, পরিবার, শ্রম ও আরও দেখুন।' },
          { title: 'আইনজীবী খুঁজুন', text: 'ডেমো আইনজীবী তালিকায় খুঁজুন।' },
        ],
      },
    },

    question: {
      placeholder: 'যেমন: কেনার আগে জমির মালিকানা কীভাবে যাচাই করব?',
      send: 'প্রশ্ন পাঠান',
      tryExample: 'একটি উদাহরণ দেখুন',
      examples: [
        'আমার জমির দলিল নিয়ে বিরোধ হলে কী করব?',
        'নিয়োগকর্তা বেতন আটকে রাখলে আমার অধিকার কী?',
        'ভোক্তা অধিকার লঙ্ঘন হলে অভিযোগ কোথায় করব?',
      ],
      disclaimer:
        'এটি শুধু শিক্ষামূলক তথ্য — আইনি পরামর্শ নয়। এর মাধ্যমে কোনো আইনজীবী-মক্কেল সম্পর্ক তৈরি হয় না।',
      emptyError: 'অনুগ্রহ করে আগে আপনার প্রশ্নটি লিখুন।',
    },

    // ---------- /assistant পাতা ----------
    assistant: {
      title: 'এআই আইনি সহকারী',
      welcomeTitle: 'আইন বুঝতে আমি কীভাবে সাহায্য করতে পারি?',
      welcomeSubtitle: 'বাংলাদেশ সম্পর্কিত সাধারণ আইনি তথ্য পান, বাংলা বা ইংরেজিতে।',
      topicsLabel: 'শুরু করতে একটি বিষয় বেছে নিন',
      topics: [
        { title: 'ভূমি ও সম্পত্তি আইন', text: 'মালিকানা, দলিল ও বিরোধ', question: 'জমি কেনার আগে কীভাবে যাচাই করব যে এর আইনি মালিক কে?' },
        { title: 'পারিবারিক আইন', text: 'বিবাহ, উত্তরাধিকার ও পারিবারিক বিরোধ', question: 'পারিবারিক সম্পত্তি বা উত্তরাধিকার নিয়ে বিরোধে আমার অধিকার কী?' },
        { title: 'শ্রম আইন', text: 'বেতন, চুক্তি ও কর্মক্ষেত্রের অধিকার', question: 'নিয়োগকর্তা সময়মতো বেতন দেননি। আমি কী করতে পারি?' },
        { title: 'ব্যবসা ও ভোক্তা অধিকার', text: 'অভিযোগ, দোকান ও সেবা', question: 'দোকান ত্রুটিপূর্ণ পণ্য বিক্রি করলে অভিযোগ কোথায় করব?' },
      ],
      sidebarLabel: 'চ্যাটের পাশের তালিকা',
      newChat: 'নতুন চ্যাট',
      searchLabel: 'কথোপকথন খুঁজুন',
      searchPlaceholder: 'কথোপকথন খুঁজুন',
      recent: 'সাম্প্রতিক চ্যাট',
      sampleTag: 'নমুনা',
      sampleNote: 'এগুলো ডেমোর জন্য নমুনা। চ্যাটের ইতিহাস এখনও সংরক্ষণ করা হয় না।',
      noMatch: 'আপনার খোঁজের সাথে কোনো কথোপকথন মেলেনি।',
      samples: [
        { title: 'জমির মালিকানা যাচাই', question: 'জমি কেনার আগে কীভাবে যাচাই করব এর মালিক কে?' },
        { title: 'বেতন বকেয়া: কী করব?', question: 'নিয়োগকর্তা আমার বেতন দেননি। আমি কী করতে পারি?' },
        { title: 'ভোক্তা অভিযোগের ধাপ', question: 'ত্রুটিপূর্ণ পণ্যের অভিযোগ কোথায় করব?' },
        { title: 'পারিবারিক বিরোধে অধিকার', question: 'পারিবারিক সম্পত্তির বিরোধে আমার অধিকার কী?' },
      ],
      guestName: 'ডেমো দর্শক',
      guestStatus: 'সাইন ইন করা নেই',
      loginLink: 'লগইন করুন',
      signedInStatus: 'সাইন ইন করা আছে',
      openMenu: 'চ্যাটের পাশের তালিকা খুলুন',
      closeMenu: 'চ্যাটের পাশের তালিকা বন্ধ করুন',
      demoPill: 'ডেমো · এআই সংযুক্ত নয়',
      conversation: 'কথোপকথন',
      placeholder: 'বাংলা বা ইংরেজিতে আপনার আইনি প্রশ্ন লিখুন…',
      send: 'পাঠান',
      sending: 'আপনার প্রশ্নটি দেখা হচ্ছে…',
      you: 'আপনি',
      assistantName: 'LawAssist AI',
      placeholderLabel: 'স্থানধারক · উত্তর নয়',
      notConnectedReply: 'এআই-এর উত্তর শীঘ্রই সংযুক্ত করা হবে।',
      errorReply: 'উত্তর আনতে গিয়ে সমস্যা হয়েছে। অনুগ্রহ করে আবার চেষ্টা করুন।',
      retry: 'আবার চেষ্টা করুন',
      sources: 'উৎস',
      hint: 'পাঠাতে এন্টার · নতুন লাইনে শিফট + এন্টার',
      disclaimer: 'এই এআই সহকারী সাধারণ আইনি তথ্য দেয়, পেশাদার আইনি পরামর্শ নয়।',
    },

    // ---------- /lawyers পাতা ----------
    lawyersPage: {
      eyebrow: 'আইনজীবী খুঁজুন',
      title: 'আইনজীবী তালিকা',
      description:
        'অনুশীলনের ক্ষেত্র, ভাষা, আদালত ও পরামর্শ ফি অনুযায়ী নমুনা আইনজীবী প্রোফাইল খুঁজুন, যাতে তালিকাটি কীভাবে কাজ করবে তা দেখা যায়।',
      demoTitle: 'শুধুই ডেমো প্রোফাইল',
      demoText:
        'এখানকার আইনজীবীরা কাল্পনিক নমুনা। এঁরা বাস্তব মানুষ নন, যাচাইকৃত নন, এবং এঁদের সাথে যোগাযোগ বা সময় নেওয়া যায় না। ফি শুধু উদাহরণ।',
      searchLabel: 'খোঁজ',
      searchPlaceholder: 'নাম, ক্ষেত্র বা আদালত খুঁজুন',
      filtersLabel: 'ফিল্টার',
      backToAll: 'সব প্রোফাইলে ফিরুন',
      detailsLabel: 'প্রোফাইলের বিবরণ',
      area: 'অনুশীলনের ক্ষেত্র',
      court: 'আদালত / এখতিয়ার',
      languages: 'ভাষা',
      format: 'পরামর্শের মাধ্যম',
      fee: 'উদাহরণ ফি',
      aboutTitle: 'এই প্রোফাইল সম্পর্কে',
      aboutText: 'এটি একটি কাল্পনিক নমুনা প্রোফাইল, শুধু তালিকাটি কেমন দেখাবে তা বোঝাতে তৈরি। এটি কোনো বাস্তব আইনজীবী নন।',
      requestConsultation: 'পরামর্শের অনুরোধ করুন',
      unavailable: 'প্রোটোটাইপে সময় নেওয়ার সুবিধা নেই।',
      notFoundTitle: 'প্রোফাইল পাওয়া যায়নি',
      notFoundText: 'এই ঠিকানায় কোনো ডেমো প্রোফাইল নেই।',
    },

    // ---------- /about পাতা ----------
    about: {
      eyebrow: 'LawAssist AI সম্পর্কে',
      line1: 'আইনি তথ্য,',
      line2: 'আপনার বোঝার ভাষায়',
      lead: 'LawAssist AI একটি বাংলাদেশি আইনি তথ্যের প্ল্যাটফর্ম। এটি সহজ বাংলা ও ইংরেজিতে আইন ব্যাখ্যা করার এবং প্রতিটি ব্যাখ্যার উৎস দেখানোর লক্ষ্য নিয়ে তৈরি হচ্ছে।',
      missionTitle: 'কেন এটি তৈরি',
      missionText:
        'বাংলাদেশের অনেক মানুষ ভূমি, পরিবার, কর্মক্ষেত্র বা ভোক্তা সমস্যায় পড়ে বুঝতে পারেন না কোথা থেকে শুরু করবেন। আইনের কঠিন ভাষা এবং বাংলা ও ইংরেজি উৎসের ব্যবধান বিষয়টি আরও কঠিন করে। LawAssist AI দুই ভাষাতেই আইনি তথ্য সহজে বোঝার সুযোগ তৈরি করতে চায়, যাতে মানুষ পদক্ষেপ নেওয়ার আগে আরও ভালো প্রশ্ন করতে পারেন।',
      howTitle: 'এটি কীভাবে সাহায্য করে',
      how: [
        { title: 'আইনি বিষয় অন্বেষণ', text: 'ভূমি, পরিবার ও শ্রমের মতো আইনের ক্ষেত্র দেখুন, সাথে সাধারণ প্রশ্ন ও সহজ ভাষার ব্যাখ্যা।' },
        { title: 'উত্তর বুঝুন', text: 'বাংলা বা ইংরেজিতে প্রশ্ন করুন এবং আইনি কঠিন শব্দ ছাড়া ব্যাখ্যা পান।' },
        { title: 'উৎস খুঁজে নিন', text: 'প্রতিটি উত্তর কোন আইন বা ধারার ওপর ভিত্তি করে, তা দেখানোর লক্ষ্য রয়েছে, যাতে আপনি নিজে পড়ে দেখতে পারেন।' },
      ],
      audienceTitle: 'কাদের জন্য',
      audience: [
        { title: 'আইনের প্রশিক্ষণ নেই এমন মানুষ', text: 'বাংলাদেশের যে কেউ, যিনি কী করবেন ঠিক করার আগে একটি আইনি প্রশ্ন বুঝতে চান।' },
        { title: 'শিক্ষার্থী ও শিক্ষানবিশ', text: 'যাঁরা মূল আইনের পাশে সহজ ভাষার ব্যাখ্যা চান।' },
        { title: 'কমিউনিটি সহায়ক', text: 'যাঁরা অন্যদের দৈনন্দিন আইনি প্রশ্নে সাহায্য করেন এবং একটি স্পষ্ট শুরুর জায়গা চান।' },
      ],
      featuresTitle: 'মূল সুবিধা',
      features: [
        { title: 'বাংলা ও ইংরেজি', text: 'যেকোনো সময় পুরো সাইট বাংলা ও ইংরেজির মধ্যে বদলান।' },
        { title: 'আইনের ক্ষেত্র', text: 'ভূমি, পরিবার ও শ্রমের মতো সাধারণ বিষয়ের শুরুর জায়গা।' },
        { title: 'এআই সহকারী', text: 'আইনি প্রশ্নের জন্য একটি চ্যাট পর্দা। এআই নিজে এখনও সংযুক্ত নয়।' },
        { title: 'আইনজীবী তালিকা', text: 'নমুনা আইনজীবী প্রোফাইল খুঁজুন ও ফিল্টার করুন। সব প্রোফাইল কাল্পনিক ডেমো।' },
      ],
      honestTitle: 'অনুগ্রহ করে পড়ুন',
      honestText:
        'LawAssist AI একটি ডেমো প্রোটোটাইপ। এটি শুধু শিক্ষামূলক তথ্য দেয়, আইনি পরামর্শ নয়, এবং যোগ্য আইনজীবীর বিকল্প নয়। এটি ব্যবহারে কোনো আইনজীবী-মক্কেল সম্পর্ক তৈরি হয় না। বাস্তব আইনি সমস্যায় লাইসেন্সপ্রাপ্ত আইনজীবীর সাথে কথা বলুন।',
      statusTitle: 'প্রোটোটাইপ এখন কোথায়',
      workingLabel: 'এখন কাজ করছে',
      working: ['বাংলা ও ইংরেজি ইন্টারফেস', 'আইনের ক্ষেত্রের সারসংক্ষেপ', 'কার্যকর ফিল্টারসহ আইনজীবী তালিকা (ডেমো প্রোফাইল)', 'সহকারীর চ্যাট পর্দা'],
      plannedLabel: 'এখনও তৈরি হয়নি',
      planned: ['এআই-তৈরি উত্তর (কোনো ব্যাকএন্ড সংযুক্ত নেই)', 'উত্তরের জন্য উৎস উল্লেখ', 'যাচাইকৃত আইনজীবীর তালিকা', 'অ্যাকাউন্ট ও লগইন'],
      ctaTitle: 'অন্বেষণ শুরু করুন',
      ctaText: 'আইনের ক্ষেত্রগুলো দেখুন, অথবা সহকারীর চ্যাট পর্দা চেষ্টা করুন।',
    },

  },
}

export default translations
