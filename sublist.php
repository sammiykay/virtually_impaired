<?php
session_start();
require_once('dbconfig.php');

// Always enable voice for this demo (remove in production if needed)
$_SESSION['voice_enabled'] = true;

$userid = $_SESSION['usersession'];
if($userid == null){
    header('Location: index.php');
    exit;
}

$result = mysqli_query($conn, "SELECT * FROM user WHERE userid = '$userid'");
if(mysqli_num_rows($result) > 0){
    $row = mysqli_fetch_assoc($result);
    
    // Get all available subjects
    $result1 = mysqli_query($conn, "SELECT * FROM lang");
    $subjects = [];
    while($row1 = mysqli_fetch_assoc($result1)){
        $subjects[] = strtolower(trim($row1['subjects']));
    }
?>

<!DOCTYPE html>
<html>
<head>
    <title>Welcome Online Exam</title>
    <link rel="stylesheet" type="text/css" href="css/bootstrap.css">
    <link rel="stylesheet" type="text/css" href="main.css">
    <link rel="stylesheet" type="text/css" href="css/font/flaticon.css">
    <link href="https://fonts.googleapis.com/css?family=Fira+Sans|Josefin+Sans" rel="stylesheet">
    <meta charset="UTF-8">
    <meta name="description" content="Online Exam">
    <meta name="author" content="Sukanya Ledalla, Akhil Regonda, Nishanth Kadapakonda">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <style>
        .subject-link {
            display: block;
            padding: 10px 15px;
            margin: 8px 0;
            background: #f8f9fa;
            border-radius: 4px;
            color: #333;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        .subject-link:hover {
            background: #e9ecef;
            transform: translateX(5px);
        }
        #voice-status {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #28a745;
            color: white;
            padding: 10px 15px;
            border-radius: 50px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            z-index: 1000;
        }
    </style>
</head>
<body>
    <div id="voice-status">Voice: Ready</div>
    
    <div class="oq-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-4">
                    <div class=""><a href="index.php"><img src="images/quiz.png" class="oq-logo"></a></div>
                </div>
                <div class="col-md-8">
                    <div class="oq-userArea pull-right">
                        <span class="oq-username"> welcome <?php echo $row['username'];?> </span>
                        <a class="btn btn-primary" href="logout.php?logout">Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="oq-menuBody">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-6 col-md-offset-3">
                    <div class="oq-menu">
                        <div id="oq-subjectsList">
                            <h3>List of subjects:</h3>
                            <div class="subject-list">
                                <?php foreach($subjects as $subject): ?>
                                    <a href="testlist.php?subject=<?php echo urlencode($subject); ?>" class="subject-link">
                                        <?php echo ucfirst($subject); ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                            <p class="mt-3">Say a subject name or "repeat" to hear options again.</p>
                        </div>
                        <div id="voice-feedback" class="mt-3 p-3 bg-light rounded"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="oq-footer">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-6"><span class="oq-footerText">ONLINE QUIZ 2023</span></div>
                <div class="col-md-6"><span class="oq-footerText pull-right">Developed by - <a href="http://akhil-regonda.azurewebsites.net/"><span class="oq-footerBy">A<span class="oq-footerSubName">khil</span> R<span class="oq-footerSubName">egonda</span></span></a></span></div>
            </div>
        </div>
    </div>

    <script src="js/jquery-3.1.1.min.js"></script>
    <script src="js/bootstrap.js"></script>
    
    <script>
    $(document).ready(function() {
        // Voice control system
        const voiceSystem = {
            enabled: <?php echo isset($_SESSION['voice_enabled']) && $_SESSION['voice_enabled'] ? 'true' : 'false'; ?>,
            subjects: <?php echo json_encode($subjects); ?>,
            synth: window.speechSynthesis,
            recognition: null,
            
            init: function() {
                if (!this.enabled) return;
                
                this.updateStatus('Initializing...');
                
                // Initialize speech synthesis
                this.loadVoices().then(() => {
                    this.speakWelcome();
                }).catch(error => {
                    console.error("Voice error:", error);
                    this.updateStatus('Voice error');
                });
            },
            
            loadVoices: function() {
                return new Promise((resolve) => {
                    let voices = this.synth.getVoices();
                    if (voices.length > 0) {
                        resolve();
                    } else {
                        this.synth.onvoiceschanged = () => {
                            voices = this.synth.getVoices();
                            resolve();
                        };
                        // Fallback timeout
                        setTimeout(resolve, 1000);
                    }
                });
            },
            
            speakWelcome: function() {
                const welcomeText = `Welcome ${$('.oq-username').text()}. ` + 
                                   `Available subjects are: ${this.subjects.join(', ')}. ` +
                                   `Say a subject name or "repeat" to hear options again.`;
                
                this.speak(welcomeText, () => {
                    this.startRecognition();
                });
            },
            
            speak: function(text, onEnd) {
                if (!this.enabled) return;
                
                this.updateStatus('Speaking...');
                this.synth.cancel(); // Cancel any ongoing speech
                
                const utterance = new SpeechSynthesisUtterance(text);
                utterance.rate = 0.9;
                utterance.pitch = 1;
                utterance.volume = 1;
                
                // Select the best voice
                const voices = this.synth.getVoices();
                for (let voice of voices) {
                    if (voice.lang.includes('en')) {
                        utterance.voice = voice;
                        break;
                    }
                }
                
                utterance.onend = () => {
                    this.updateStatus('Listening...');
                    if (onEnd) onEnd();
                };
                
                utterance.onerror = (event) => {
                    console.error('Speech error:', event);
                    this.updateStatus('Error');
                };
                
                this.synth.speak(utterance);
            },
            
            startRecognition: function() {
                if (!('webkitSpeechRecognition' in window)) {
                    this.updateStatus('No speech API');
                    return;
                }
                
                this.recognition = new (window.SpeechRecognition || window.webkitSpeechRecognition)();
                this.recognition.continuous = true;
                this.recognition.interimResults = false;
                this.recognition.lang = 'en-US';
                this.recognition.maxAlternatives = 1;
                
                this.recognition.onresult = (event) => {
                    const transcript = event.results[event.results.length-1][0].transcript.trim().toLowerCase();
                    $('#voice-feedback').html(`You said: <strong>${transcript}</strong>`);
                    this.processCommand(transcript);
                };
                
                this.recognition.onerror = (event) => {
                    console.log('Recognition error:', event.error);
                    this.updateStatus('Error');
                    setTimeout(() => this.startRecognition(), 1000);
                };
                
                this.recognition.onend = () => {
                    this.updateStatus('Reconnecting...');
                    this.startRecognition();
                };
                
                this.recognition.start();
                this.updateStatus('Listening...');
            },
            
            processCommand: function(command) {
                // Check for subject match
                for (let subject of this.subjects) {
                    if (command.includes(subject)) {
                        this.speak(`Opening ${subject} tests.`, () => {
                            window.location.href = `testlist.php?subject=${encodeURIComponent(subject)}`;
                        });
                        return;
                    }
                }
                
                // Special commands
                if (command.includes('repeat')) {
                    this.speakWelcome();
                    return;
                }
                
                if (command.includes('logout')) {
                    this.speak('Logging out.', () => {
                        window.location.href = 'logout.php';
                    });
                    return;
                }
                
                // Default response
                this.speak("Sorry, I didn't understand. Please try again.");
            },
            
            updateStatus: function(status) {
                $('#voice-status').text(`Voice: ${status}`);
            }
        };
        
        // Initialize voice system on page load
        // Gesture-triggered voice system after window load + delay
// Autoplay speech on first user gesture (tap or click)
window.onload = function () {
    const promptMessage = 'Voice: Tap anywhere to begin';
    $('#voice-status').text(promptMessage);

    function startVoiceOnce() {
        $('#voice-status').text('Voice: Initializing...');
        
        setTimeout(() => {
            voiceSystem.init();
        }, 500); // Small delay for smoother activation

        // Remove gesture listeners after first trigger
        document.body.removeEventListener('click', startVoiceOnce);
        document.body.removeEventListener('touchstart', startVoiceOnce);
        document.body.removeEventListener('keydown', startVoiceOnce);
    }

    // Add gesture listeners to allow speech activation
    document.body.addEventListener('click', startVoiceOnce);
    document.body.addEventListener('touchstart', startVoiceOnce);
    document.body.addEventListener('keydown', startVoiceOnce);
};

        
        // Ensure voice works on link clicks
        $('a').on('click', function(e) {
            if (!$(this).hasClass('subject-link')) {
                voiceSystem.enabled = false;
                voiceSystem.synth.cancel();
                if (voiceSystem.recognition) {
                    voiceSystem.recognition.stop();
                }
            }
        });
    });
    </script>
</body>
</html>

<?php
}
?>