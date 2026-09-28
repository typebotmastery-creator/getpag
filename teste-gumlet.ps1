param(
    [Parameter(Position = 0)]
    [string]$Link = "https://video.gumlet.io/6928cc634384371c9e80d112/6a33318f6a4f24de8a433660/main.m3u8"
)

# ==========================================================================
#  Testa se um link de video do Gumlet ainda esta valendo
#
#  Dois detalhes que fazem este teste funcionar:
#
#  1) Nao basta testar so o main.m3u8. Ele responde 200 mesmo com o link
#     vencido, porque o token nao esta no manifesto - esta nos arquivos de
#     video, um nivel mais para dentro. E preciso descer ate eles.
#
#  2) Invoke-WebRequest devolve .m3u8 como byte[] (o PowerShell nao sabe que
#     e texto). O regex em byte[] nao acha nada. Por isso aqui se usa
#     curl.exe, que sempre devolve texto.
# ==========================================================================

$ErrorActionPreference = 'Stop'

function Get-Text($url) {
    $tmp = [IO.Path]::GetTempFileName()
    try {
        & curl.exe -s --max-time 25 -o $tmp $url
        return [IO.File]::ReadAllText($tmp, [Text.Encoding]::UTF8)
    } finally {
        Remove-Item $tmp -Force -ErrorAction SilentlyContinue
    }
}

function Get-Codigo($url) {
    $v = & curl.exe -s -o NUL -w "%{http_code}" --max-time 25 $url
    if ($v -match '^\d+$') { return [int]$v }
    return 0
}

Write-Host ""
Write-Host "  Testando link do Gumlet" -ForegroundColor Cyan
Write-Host "  ------------------------------------------------------------"

$base = $Link.Substring(0, $Link.LastIndexOf('/') + 1)
Write-Host ("  Link: {0}" -f $Link)

# --- 1) o manifesto abre? ---
$codigo = Get-Codigo $Link
Write-Host ("  1. Manifesto ............ HTTP {0}" -f $codigo) -ForegroundColor DarkGray
if ($codigo -ne 200) {
    Write-Host ""
    if ($codigo -eq 403) {
        Write-Host "  RESULTADO: o link VENCEU ou esta bloqueado (403)." -ForegroundColor Red
    } elseif ($codigo -eq 401) {
        Write-Host "  RESULTADO: o Gumlet exige token (401)." -ForegroundColor Red
    } else {
        Write-Host "  RESULTADO: a URL nao abre (HTTP $codigo)." -ForegroundColor Red
    }
    Write-Host ""
    pause
    exit 1
}

$manifesto = Get-Text $Link

# --- 2) desce um nivel: as qualidades citadas no manifesto ---
$variantes = [regex]::Matches($manifesto, '([A-Za-z0-9_\-\.]+\.m3u8)') |
            ForEach-Object { $_.Groups[1].Value } |
            Where-Object { $_ -notmatch '_iframe' } |
            Select-Object -Unique

$todasExpiracoes = @()
$achouAlgumArquivo = $false
$testouDownload = $false
$codDownload = 0

foreach ($v in $variantes) {
    $txt = Get-Text ($base + $v)
    if (-not $txt) { continue }
    $achouAlgumArquivo = $true

    $e = [regex]::Matches($txt, 'expires=(\d+)') | ForEach-Object { [int64]$_.Groups[1].Value }
    if ($e) { $todasExpiracoes += $e }

    # baixa um pedaco real do video, para o Gumlet dizer se aceita
    if (-not $testouDownload) {
        $seg = [regex]::Match($txt, '([A-Za-z0-9_\-\.]+\.mp4\?[^"]+)')
        if ($seg.Success) {
            $testouDownload = $true
            $codDownload = Get-Codigo ($base + ($seg.Groups[1].Value -replace '&amp;', '&'))
        }
    }
}

# --- 3) sem token? entao nunca vence ---
if (-not $achouAlgumArquivo) {
    Write-Host "  2. Arquivos de video ... nao consegui ler a lista" -ForegroundColor Yellow
    Write-Host ""
    Write-Host "  RESULTADO: inconclusivo. O link respondeu, mas nao consegui" -ForegroundColor Yellow
    Write-Host "  abrir os arquivos de video." -ForegroundColor Yellow
    Write-Host ""
    pause
    exit 1
}

if ($todasExpiracoes.Count -eq 0) {
    Write-Host "  2. Arquivos de video ... sem token" -ForegroundColor DarkGray
    Write-Host "  3. Baixa real do video .. HTTP $codDownload" -ForegroundColor DarkGray
    Write-Host ""
    if ($codDownload -in 200, 206) {
        Write-Host "  RESULTADO: FUNCIONANDO e nunca vai vencer." -ForegroundColor Green
        Write-Host "  Link sem prazo. Pode usar nas aulas." -ForegroundColor Green
    } else {
        Write-Host "  RESULTADO: o Gumlet recusou o download (HTTP $codDownload)." -ForegroundColor Red
    }
    Write-Host ""
    pause
    exit 0
}

# --- 4) com token: quando vence? ---
$exps = @($todasExpiracoes | Sort-Object -Unique)
$prazoOK = $true
Write-Host "  2. Arquivos de video ... tem token" -ForegroundColor DarkGray
foreach ($e in $exps) {
    $quando = [DateTimeOffset]::FromUnixTimeSeconds($e).ToLocalTime()
    $dias = [Math]::Floor(($quando - (Get-Date)).TotalDays)
    $txt = $quando.ToString("dd/MM/yyyy")
    if ($dias -lt 0) {
        Write-Host ("     Vence em  {0}   JA VENCEU ha {1} dias" -f $txt, [Math]::Abs($dias)) -ForegroundColor Red
        $prazoOK = $false
    } else {
        Write-Host ("     Vence em  {0}   faltam {1} dias" -f $txt, $dias) -ForegroundColor Yellow
    }
}
Write-Host ("  3. Baixa real do video .. HTTP {0}" -f $codDownload) -ForegroundColor DarkGray

Write-Host ""
if (-not $prazoOK) {
    Write-Host "  RESULTADO: JA VENCEU. A aula nao vai tocar." -ForegroundColor Red
    Write-Host "  Troque o link por um novo sem prazo, ou desligue o" -ForegroundColor Red
    Write-Host "  Signed URL no painel do Gumlet." -ForegroundColor Red
} elseif ($codDownload -in 200, 206) {
    Write-Host "  RESULTADO: FUNCIONANDO, mas o link vence." -ForegroundColor Green
    Write-Host "  Da para cadastrar a aula hoje. Antes de vender, troque por" -ForegroundColor Yellow
    Write-Host "  um link sem prazo (ou desligue o Signed URL no Gumlet)." -ForegroundColor Yellow
} else {
    Write-Host "  RESULTADO: o Gumlet recusou o download (HTTP $codDownload)." -ForegroundColor Red
    if ($codDownload -eq 401) {
        Write-Host "  Signed URL esta ligado. Desligue no painel do Gumlet," -ForegroundColor Yellow
        Write-Host "  ou use a URL sem token." -ForegroundColor Yellow
    }
}
Write-Host ""
pause
