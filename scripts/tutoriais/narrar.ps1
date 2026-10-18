param([string[]]$Video)
$ErrorActionPreference='Stop'
Add-Type -AssemblyName System.Speech
$tutorialNarrator=New-Object System.Speech.Synthesis.SpeechSynthesizer
$tutorialNarrator.SelectVoice('Microsoft Maria Desktop')
$tutorialNarrator.Rate=0
$scenes=Get-Content -Raw -Encoding utf8 tmp/tutoriais-2026/narracoes.json | ConvertFrom-Json
foreach($scene in $scenes){
 if($Video.Count -gt 0 -and $scene.video -notin $Video){continue}
 $target=Join-Path (Get-Location) ('tmp/tutoriais-2026/{0}-{1:d2}.wav' -f $scene.video,[int]$scene.id)
 $tutorialNarrator.SetOutputToWaveFile($target)
 $tutorialNarrator.Speak($scene.speech)
 Write-Output ('Narração: {0} cena {1}' -f $scene.video,$scene.id)
}
$tutorialNarrator.Dispose()
