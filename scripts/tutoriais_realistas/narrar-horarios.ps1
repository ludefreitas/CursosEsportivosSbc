param([ValidateSet('horario-semanal','horario-especial')][string]$Tipo)
$demoDir = Join-Path (Get-Location) ('tmp/tutorial-' + $Tipo)
$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.Runtime.WindowsRuntime
$null = [Windows.Media.SpeechSynthesis.SpeechSynthesizer, Windows.Media.SpeechSynthesis, ContentType=WindowsRuntime]
$null = [Windows.Media.SpeechSynthesis.SpeechSynthesisStream, Windows.Media.SpeechSynthesis, ContentType=WindowsRuntime]
$null = [Windows.Storage.Streams.DataReader, Windows.Storage.Streams, ContentType=WindowsRuntime]
$demoAsTask = [System.WindowsRuntimeSystemExtensions].GetMethods() | Where-Object { $_.Name -eq 'AsTask' -and $_.IsGenericMethodDefinition -and $_.GetGenericArguments().Count -eq 1 -and $_.GetParameters().Count -eq 1 } | Select-Object -First 1
function Wait-SpeechTask($operation, [type]$resultType) {
  $demoTask = $demoAsTask.MakeGenericMethod($resultType).Invoke($null, @($operation))
  $demoTask.GetAwaiter().GetResult()
}
$demoSynth = New-Object Windows.Media.SpeechSynthesis.SpeechSynthesizer
$demoSynth.Voice = [Windows.Media.SpeechSynthesis.SpeechSynthesizer]::AllVoices | Where-Object Language -eq 'pt-BR' | Select-Object -First 1
$demoSynth.Voice.DisplayName
Copy-Item -LiteralPath ('scripts/tutoriais_realistas/'+$Tipo+'-cenas.json') -Destination (Join-Path $demoDir 'cenas.json')
$scenes = Get-Content -LiteralPath (Join-Path $demoDir 'cenas.json') -Encoding utf8 -Raw | ConvertFrom-Json
foreach ($scene in $scenes) {
    $speechStream = Wait-SpeechTask ($demoSynth.SynthesizeTextToStreamAsync($scene.speech)) ([Windows.Media.SpeechSynthesis.SpeechSynthesisStream])
    $reader = New-Object Windows.Storage.Streams.DataReader($speechStream)
    $length = Wait-SpeechTask ($reader.LoadAsync([uint32]$speechStream.Size)) ([uint32])
    $bytes = New-Object byte[] $length
    $reader.ReadBytes($bytes)
    [IO.File]::WriteAllBytes((Join-Path $demoDir ($scene.id + '.wav')), $bytes)
    $reader.Dispose()
    $speechStream.Dispose()
}
