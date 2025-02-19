
<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $userInput = "";
    
    // Handle voice input
    if (!empty($_POST["voice_input"])) {
        $userInput = $_POST["voice_input"];
    }
    
    // Handle voice file
    if (!empty($_FILES["audio_file"]["tmp_name"])) {
        $audioFile = $_FILES["audio_file"]["tmp_name"];
        $outputFile = "converted_audio.wav";

        // Convert audio file to WAV format using ffmpeg
        exec("ffmpeg -i " . escapeshellarg($audioFile) . " -ar 16000 -ac 1 -c:a pcm_s16le " . escapeshellarg($outputFile));
        
        if (file_exists($outputFile)) {
            $apiKey = "YOUR_GOOGLE_API_KEY";
            $audioData = file_get_contents($outputFile);
            $base64Audio = base64_encode($audioData);

            $request = [
                "config" => [
                    "encoding" => "LINEAR16",
                    "sampleRateHertz" => 16000,
                    "languageCode" => "en-US"
                ],
                "audio" => [
                    "content" => $base64Audio
                ]
            ];

            $jsonRequest = json_encode($request);
            $url = "https://speech.googleapis.com/v1/speech:recognize?key=$apiKey";

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonRequest);

            $response = curl_exec($ch);
            curl_close($ch);

            $responseArray = json_decode($response, true);
            
            $transcribedText = $responseArray['results'][0]['alternatives'][0]['transcript'] ?? '';
            $userInput .= " " . $transcribedText;

            // Remove temporary file
            unlink($outputFile);
        }
    }
    
    // Extract date and reason using regex
    preg_match_all('/\b(January|February|March|April|May|June|July|August|September|October|November|December)\s\d{1,2},\s\d{4}\b/', $userInput, $dates);
    
    $reason = "General Appointment";
    if (stripos($userInput, "health") !== false) {
        $reason = "Health Issues";
    } elseif (stripos($userInput, "personal") !== false) {
        $reason = "Personal Reasons";
    }
    
    // Sorting Dates
    if (!empty($dates[0])) {
        sort($dates[0]);
        
        $startDate = date("Ymd", strtotime($dates[0][0]));
        $endDate = date("Ymd", strtotime(end($dates[0])));

        // Save appointment details in a file
        $appointmentData = "Start Date: " . $dates[0][0] . "\n";
        $appointmentData .= "End Date: " . end($dates[0]) . "\n";
        $appointmentData .= "Reason: " . $reason . "\n\n";
        file_put_contents("appointments.txt", $appointmentData, FILE_APPEND);
        
        // Generate Google Calendar link
        $gCalUrl = "https://www.google.com/calendar/render?action=TEMPLATE&text=" . urlencode($reason) . "&dates=" . $startDate . "/" . $endDate . "&details=Appointment%20Scheduled";
    } else {
        $gCalUrl = "";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Appointment Scheduler</title>
    <script>
        function startDictation() {
            if (window.hasOwnProperty('webkitSpeechRecognition')) {
                var recognition = new webkitSpeechRecognition();
                recognition.continuous = false;
                recognition.interimResults = false;
                recognition.lang = "en-US";
                recognition.start();

                recognition.onresult = function(event) {
                    document.getElementById('voice_input').value = event.results[0][0].transcript;
                    recognition.stop();
                };

                recognition.onerror = function(event) {
                    recognition.stop();
                }
            }
        }
    </script>
</head>
<body>
    <h2>Enter Appointment Details</h2>
    <form method="post" enctype="multipart/form-data">
        <input type="text" id="voice_input" name="voice_input" placeholder="Enter or speak your appointment details...">
        <button type="button" onclick="startDictation()">🎤 Speak</button>
        <br><br>
        <label>Upload Voice File:</label>
        <input type="file" name="audio_file" accept="audio/*">
        <br><br>
        <input type="submit" value="Submit">
    </form>
    
    <?php if ($_SERVER["REQUEST_METHOD"] == "POST" && !empty($dates[0])): ?>
        <h2>Appointment Details</h2>
        <p><strong>Start Date:</strong> <?php echo $dates[0][0]; ?></p>
        <p><strong>End Date:</strong> <?php echo end($dates[0]); ?></p>
        <p><strong>Reason:</strong> <?php echo $reason; ?></p>
        <p><a href="<?php echo $gCalUrl; ?>" target="_blank">Add to Google Calendar</a></p>
    <?php endif; ?>
</body>
</html>
