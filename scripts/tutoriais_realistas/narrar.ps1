$ErrorActionPreference='Stop'
Add-Type -AssemblyName System.Speech
$voice=New-Object System.Speech.Synthesis.SpeechSynthesizer
$voice.SelectVoice('Microsoft Maria Desktop')
$voice.Rate=1
$scenes=Get-Content -Raw -Encoding utf8 scripts/tutoriais_realistas/cadastro-cenas.json|ConvertFrom-Json
foreach($scene in $scenes){$voice.SetOutputToWaveFile((Join-Path (Get-Location) ('tmp/tutorial-realista/'+$scene.id+'.wav')));$voice.Speak($scene.speech);Write-Output $scene.id}
$voice.Dispose()
