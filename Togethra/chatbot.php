<?php
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // --- [1] OUTPUT the HTML Page with Messenger-like UI ---
    ?>
    <!DOCTYPE html>
    <html>
    <head>
      <meta charset="utf-8" />
      <title>TogetherA+ Chatbot</title>
      <link rel="stylesheet" href="chatbot.css">

    </head>
    <body>      
      <!-- Toggle TTS button -->
      <button id="ttsToggleBtn">Enable TTS</button>

      <!-- Voice selection dropdown + button to confirm choice -->
      <div style="margin-top: 10px;">
        <label for="voiceSelect" id = "choosevoice">Choose Voice:</label>
        <select id="voiceSelect"></select>
        <button id="changeVoiceBtn">Change Voice</button>
      </div>

      <div class="chatbox">
        <!-- Where messages will appear -->
        <div id="chatLog"></div>

        <!-- Text input, mic button, send button -->
        <div class="inputRow">
          <input type="text" id="userInput" placeholder="Type your message...">
          <!-- Microphone button -->
          <button id="voiceBtn" title="Voice Input">🎤</button>
          <!-- Send button -->
          <button id="sendBtn" title="Send Message">Send</button>
        </div>
      </div>

      <script>
        // DOM elements
        const chatLog       = document.getElementById('chatLog');
        const userInput     = document.getElementById('userInput');
        const sendBtn       = document.getElementById('sendBtn');
        const voiceBtn      = document.getElementById('voiceBtn');
        const ttsToggleBtn  = document.getElementById('ttsToggleBtn');
        const voiceSelect   = document.getElementById('voiceSelect');
        const changeVoiceBtn= document.getElementById('changeVoiceBtn');

        // SpeechRecognition setup
        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        let recognition = null;

        if (SpeechRecognition) {
          recognition = new SpeechRecognition();
          recognition.lang = 'en-US';
          recognition.continuous = false;

          recognition.onresult = (event) => {
            const speechToText = event.results[0][0].transcript;
            handleSend(speechToText);
          };

          recognition.onerror = (event) => {
            console.error("Voice recognition error:", event.error);
          };

          recognition.onend = () => {
            console.log("Voice recognition ended.");
          };

          voiceBtn.addEventListener('click', () => {
            console.log("Starting voice recognition...");
            recognition.start();
          });
        } else {
          // If no Web Speech support
          voiceBtn.disabled = true;
          voiceBtn.title = "Not supported in this browser";
        }

        // TTS toggle
        let ttsEnabled = false;
        ttsToggleBtn.addEventListener('click', () => {
          ttsEnabled = !ttsEnabled;
          ttsToggleBtn.textContent = ttsEnabled ? "Disable TTS" : "Enable TTS";
        });

        // Voice list & chosen voice
        let voices       = [];
        let chosenVoice  = null; // selected by user when they click "Change Voice"

        // Populate the dropdown with available voices
        function populateVoiceList() {
          voices = speechSynthesis.getVoices();
          voiceSelect.innerHTML = ''; // Clear old entries

          // If no voices, add a placeholder
          if (!voices.length) {
            const opt = document.createElement('option');
            opt.textContent = 'No voices available (or not loaded yet)';
            voiceSelect.appendChild(opt);
            return;
          }

          voices.forEach((voice, index) => {
            const opt = document.createElement('option');
            opt.value = index;
            opt.textContent = voice.name + ' (' + voice.lang + ')';
            if (voice.default) {
              opt.textContent += ' [default]';
            }
            voiceSelect.appendChild(opt);
          });
        }

        // Event fires when voices are (re)loaded
        speechSynthesis.onvoiceschanged = populateVoiceList;

        // Also populate immediately in case onvoiceschanged didn't fire yet
        populateVoiceList();

        // Change Voice button
        changeVoiceBtn.addEventListener('click', () => {
          const index = voiceSelect.selectedIndex;
          if (index >= 0 && index < voices.length) {
            chosenVoice = voices[index];
            alert(`Voice changed to: ${chosenVoice.name} (${chosenVoice.lang})`);
          } else {
            chosenVoice = null;
            alert("No valid voice selected.");
          }
        });

        // TTS speak function
        function speak(text) {
          if (!text) return;
          const utterance = new SpeechSynthesisUtterance(text);

          // If user changed voice, use it
          if (chosenVoice) {
            utterance.voice = chosenVoice;
          }
          // (Optional) tweak rate/pitch
          // utterance.rate = 1;
          // utterance.pitch = 1;

          speechSynthesis.speak(utterance);
        }

        // Display a message in chat
        function appendMessage(sender, text) {
          const p = document.createElement('p');
          p.textContent = sender + ": " + text;
          chatLog.appendChild(p);
          chatLog.scrollTop = chatLog.scrollHeight;
        }

        // Send button logic
        sendBtn.addEventListener('click', () => handleSend());

        // Enter key also sends
        userInput.addEventListener('keydown', (e) => {
          if (e.key === 'Enter') {
            e.preventDefault();
            handleSend();
          }
        });

        // Handle sending text to the chatbot
        async function handleSend(overrideText = "") {
          // If overrideText is provided (from voice), use it, else read text box
          const message = overrideText ? overrideText : userInput.value.trim();
          if (!message) return;

          appendMessage('You', message);
          userInput.value = '';

          try {
            const response = await fetch('chatbot.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ message })
            });

            if (!response.ok) {
              throw new Error("Error: " + response.statusText);
            }

            const data = await response.json();
            const botReply = data.response || data.fallback_response || "No response";

            appendMessage('Helper', botReply);

            // Only speak if TTS is enabled
            if (ttsEnabled) {
              speak(botReply);
            }

          } catch (err) {
            console.error(err);
            appendMessage('Helper', "Oops, something went wrong!");
          }
        }

        // Show an initial greeting
        appendMessage('Helper', "Hello! I’m here to help you explore all that TogetherA+ has to offer. How can I assist you today?");
      </script>
    </body>
    </html>
    <?php
    // Stop the script so we don't run chatbot logic on GET
    exit;
}

// ---------------- [2] IF POST => RUN YOUR CHATBOT LOGIC -------------------
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

function writeLog($message) {
    file_put_contents('debug.log', date('Y-m-d H:i:s') . ": " . $message . "\n", FILE_APPEND);
}

// Predefined responses
$quick_responses = [
    'shipping' => 'Shipping usually takes 2-3 business days.',
    //'return' => 'You can return items within 30 days of purchase.',
    'payment' => 'We accept credit cash, cards and PayPal.',
    'contact' => 'You can reach our support team at togethera@gmail.com'
];

// Curated responses for TogetherA+
$curated_responses = [
    'mission' => 'At TogetherA+, our mission is to empower communities by creating opportunities and solutions that uplift both individuals and communities',
    'vision'  => 'Our vision is to become the leading platform for connecting communities and to create a positive change',
    'purpose' => 'Our purpose is to unite diverse voices and foster innovation through seamless collaboration',
    'ethos'   => 'We believe in empathy, transparency, and inclusion as the foundation of everything we do at TogetherA+',
    'values'  => 'Our core values revolve around respect, integrity, and the collective pursuit of social impact',
    'culture' => 'We nurture a culture of open-minded exploration, continuous learning, and supportive teamwork',
    'promise' => 'We promise to continually evolve our platform, ensuring every individual can be heard and empowered',
    'goal'    => 'Our goal is to spark positive transformations in local and global communities through meaningful connections',
    'principles' => 'We uphold principles of fairness, cooperation, and empowerment to guide our daily actions',
    'foundation' => 'Our foundation is built on trust, driven by a heartfelt commitment to uplifting communities everywhere',
    'commitment' => 'We stay committed to bridging gaps and creating pathways that benefit all stakeholders equally',
    'philosophy' => 'Our philosophy is rooted in the belief that every interaction can foster growth and catalyze change',
    'approach' => 'We take a human-centric approach, harnessing technology to break barriers and unite communities',
    'impact'       => 'Our impact is measured by the long-term, positive changes we enable within diverse communities',
    'inclusion'    => 'We champion inclusion, believing that every voice matters and every contribution counts',
    'partnership'  => 'We form partnerships that amplify collective strengths and drive far-reaching social results',
    'innovation'   => 'Through innovation, we unlock new potential and address challenges with creative, sustainable solutions',
    'collaboration'=> 'Collaboration lies at the heart of our work, forging connections that fuel progress and shared prosperity',
    //'service'      => 'We are driven by service to others, finding purpose in meeting needs and exceeding expectations',
    'empowerment'  => 'Empowerment is our key to unleashing human potential, granting people the confidence and support they deserve',
    'growth'       => 'We foster growth by encouraging bold ideas, nurturing talent, and adapting to the evolving needs of our world',
    'sustainability'=> 'Sustainability guides our decisions, ensuring that today’s efforts benefit the generations of tomorrow',
    'excellence'   => 'We aspire to excellence, continually refining our processes to deliver the highest quality experiences',
    'compassion'   => 'Compassion underscores our actions, reminding us to listen, care, and advocate for one another',
    'integrity'    => 'We uphold integrity by remaining transparent, ethical, and true to our commitments at every step',
    'connectivity' => 'Connectivity powers our platform, linking people and ideas to spark greater collaboration and impact',
    'outreach'     => 'We expand our outreach tirelessly, striving to reach underserved areas and lift marginalized communities',
    'solidarity'   => 'We stand in solidarity with all those who share our dream of creating a more equitable, inclusive society',
    'awareness'    => 'Raising awareness is vital; we shine a light on social issues, prompting constructive dialogue and action',
    'ambition'     => 'We cultivate ambition by embracing challenges and pursuing bold visions for the future',
    'strategy'     => 'Our strategy aligns with community needs, forging clear paths to action and measurable success',
    //'language'     => 'Sure, I can assist you with that',
    'leadership'   => 'We practice leadership that inspires trust, fosters accountability, and paves the way for lasting unity',
    'philanthropy' => 'Philanthropy drives our dedication to giving back, channeling resources toward causes that need them most',
    'synergy' => 'We harness synergy to combine strengths, forging stronger bonds and deeper collaboration',
    'stewardship' => 'We practice responsible stewardship, ensuring our resources, time, and efforts serve the greater good',
    'resilience' => 'We champion resilience by learning from challenges and emerging more united than ever',
    'altruism' => 'We value altruism as a guiding principle, encouraging generosity and compassion within our networks',
    'inclusivity' => 'We embrace inclusivity, honoring diverse viewpoints and fostering a space where all can thrive',
    'equity' => 'We commit to equity by breaking barriers and distributing opportunities in a fair, balanced manner',
    'mindfulness' => 'We encourage mindfulness, staying present and aware of our community’s needs at every step',
    'empathy' => 'We cultivate empathy, listening with open hearts to understand and address the concerns of those around us',
    'momentum' => 'We build momentum through small wins, believing each step forward can spark greater achievements',
    'unity' => 'We stand for unity, bridging differences and aligning community goals to achieve shared victories',
    'bridge' => 'We serve as a bridge between individuals and resources, empowering each to reach their full potential',
    'kindness' => 'We uphold kindness as the driving force behind constructive interactions and meaningful relationships',
    'hi' => 'Hi there! How can I assist you with TogetherA+ today?',
    'hello' => 'Hello! I’m here to help. What would you like to know about TogetherA+?',
    'who are you' => 'I’m your friendly TogetherA+ assistant, here to guide you and answer any questions you may have!'
    // Add more as needed
];

// Quick response check
function checkQuickResponse($message) {
    global $quick_responses;
    foreach ($quick_responses as $keyword => $response) {
        if (stripos($message, $keyword) !== false) {
            return $response;
        }
    }
    return null;
}

// Curated response check
function checkCuratedResponse($message) {
    global $curated_responses;
    foreach ($curated_responses as $keyword => $response) {
        if (stripos($message, $keyword) !== false) {
            return $response;
        }
    }
    return null;
}

writeLog('Script started');

// Hugging Face token
$token = "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";

try {
    $json = file_get_contents('php://input');
    writeLog('Received data: ' . $json);

    $data = json_decode($json, true);
    if (!isset($data['message'])) {
        throw new Exception('No message provided');
    }

    // 1) Curated
    $curated_response = checkCuratedResponse($data['message']);
    if ($curated_response) {
        echo json_encode(['response' => $curated_response]);
        writeLog('Curated response sent');
        exit;
    }

    // 2) Quick
    $quick_response = checkQuickResponse($data['message']);
    if ($quick_response) {
        echo json_encode(['response' => $quick_response]);
        writeLog('Quick response sent');
        exit;
    }

    // 3) Fallback => Hugging Face we use blenderbot
    //$ch = curl_init('https://api-inference.huggingface.co/models/facebook/blenderbot-400M-distill');
    //$ch = curl_init('https://api-inference.huggingface.co/models/google/gemma-7b');
    $ch = curl_init('https://api-inference.huggingface.co/models/mistralai/Mistral-7B-Instruct-v0.2/v1/chat/completions');
    //$ch = curl_init('https://api-inference.huggingface.co/models/tiiuae/falcon-7b-instruct');

    $systemPrompt = "You are a helpful customer service agent of TogetherA+ who always provides clear, concise and precise answers. TogetherA+ is a web platform designed to empower individuals with disabilities by connecting them with tailored support services and resources. It focuses on fostering independence, dignity, and inclusivity while creating job opportunities for helpers. Through features like task matching, accessibility options, trusted contacts, and a payment system, it aims to make life more accessible and equitable for its users and that will soon be deployed in the market and is currently in the testing phase has many features ready to go and are implemented with all kind of resources available for any kind of disabilities through our resources website and we are always updating and adding more resources and any kind of instruction is available just guide the users to our resources tab and if unavailable point them to our ask for resources section so we can quickly add those as well and do understand most of the features are done through helpers who will help the disabled individuals by connecting with them and Please provide a concise explanation, no more than a few sentences and dont repeat too much the same words or same messages unless the same things are asked again";
    $userMessage  = $data['message'];
    
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            // Instead of "inputs", use "messages"
            'messages' => [
                [
                    'role'    => 'system',
                    'content' => $systemPrompt
                ],
                [
                    'role'    => 'user',
                    'content' => $userMessage
                ]
            ],
    
            // Often, for chat endpoints, generation parameters go at top-level
            'max_length' => 200,
            'temperature' => 0.7,
            'top_p' => 0.9,
            'max_tokens'=> 150,
            'do_sample' => true
        ]),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ],
        CURLOPT_TIMEOUT => 15
    ]);
    $response = curl_exec($ch);
    writeLog('Raw API Response: ' . $response);

    if (curl_errno($ch)) {
        throw new Exception('cURL error: ' . curl_error($ch));
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($httpCode === 503) {
        $result = json_decode($response, true);
        if (isset($result['estimated_time'])) {
            throw new Exception('Model is warming up. Please try again later.');
        }
    }

    if ($httpCode !== 200) {
        throw new Exception('API returned HTTP code ' . $httpCode);
    }

    curl_close($ch);

    $result = json_decode($response, true);

    if (isset($result['choices']) && isset($result['choices'][0]['message']['content'])) {
      // This is the new place to get the text
      $bot_response = $result['choices'][0]['message']['content'];
  } else {
      throw new Exception('Unexpected API response format');
  }
  echo json_encode(['response' => $bot_response]);
exit;

} catch (Exception $e) {
    writeLog('Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error'             => $e->getMessage(),
        'fallback_response' => 'I apologize, but I\'m having trouble right now. Please try again in a moment.'
    ]);
}
?>
