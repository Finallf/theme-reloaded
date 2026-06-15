# ============================================================================
# test-admin-access.ps1
# ----------------------------------------------------------------------------
# Tests that all theme admin endpoints reject unauthenticated access.
# Covers the "Direct admin URL access without login" item of Wave 9 C.
#
# Usage:
#   .\test-admin-access.ps1                          # default: sandbox
#   .\test-admin-access.ps1 -BaseUrl "https://reloaded.com.br"   # prod
#
# Expected outcome:
#   - Admin endpoints (admin-post, admin-ajax non-nopriv, REST manage_options)
#     must return 401/403/400, or a 3xx redirect AWAY from /wp-admin/ (to
#     wp-login.php by default, or to home when the theme's Hide Login is on)
#   - Public endpoints (nopriv, __return_true) must respond normally
#     (200/400 depending on input - but NEVER 401/403 due to missing auth)
# ============================================================================

param(
    [string]$BaseUrl = "https://theme.reloaded.com.br"
)

$ErrorActionPreference = "SilentlyContinue"

# Endpoint registry. Type 'admin' = must reject, 'public' = open by design.
$endpoints = @(
    # Endpoints that MUST reject access without auth
    @{ Type='admin'; Method='POST'; Url='/wp-admin/admin-post.php?action=rd_backup_export'; Name='admin_post: rd_backup_export' }
    @{ Type='admin'; Method='POST'; Url='/wp-admin/admin-ajax.php?action=rd_img_regenerate'; Name='wp_ajax: rd_img_regenerate' }
    @{ Type='admin'; Method='POST'; Url='/wp-json/rd/v1/backup/preview'; Name='REST: rd/v1/backup/preview' }
    @{ Type='admin'; Method='POST'; Url='/wp-json/rd/v1/backup/import'; Name='REST: rd/v1/backup/import' }
    @{ Type='admin'; Method='POST'; Url='/wp-json/rd/v1/backup/restore'; Name='REST: rd/v1/backup/restore' }

    # WordPress admin pages (must redirect to login)
    @{ Type='admin'; Method='GET'; Url='/wp-admin/'; Name='wp-admin root' }
    @{ Type='admin'; Method='GET'; Url='/wp-admin/admin.php?page=rd_options'; Name='Panel: rd_options (Geral)' }
    @{ Type='admin'; Method='GET'; Url='/wp-admin/admin.php?page=rd_options&tab=privacidade'; Name='Panel: rd_options/privacidade' }
    @{ Type='admin'; Method='GET'; Url='/wp-admin/options-general.php'; Name='WP Settings General' }
    @{ Type='admin'; Method='GET'; Url='/wp-admin/edit-comments.php'; Name='WP Comments admin' }

    # PUBLIC endpoints by design (must NOT return 401/403 for missing auth)
    @{ Type='public'; Method='POST'; Url='/wp-admin/admin-ajax.php?action=rd_search_redistribute'; Name='wp_ajax_nopriv: rd_search_redistribute' }
    @{ Type='public'; Method='POST'; Url='/wp-admin/admin-ajax.php?action=rd_track_view'; Name='wp_ajax_nopriv: rd_track_view' }
    @{ Type='public'; Method='POST'; Url='/wp-json/rd/v1/csp-report'; Name='REST: rd/v1/csp-report (browser submit)' }
)

# Helper: HTTP request without cookies/auth, returns status code + location
function Test-Endpoint {
    param([string]$Url, [string]$Method)
    try {
        $request = [System.Net.HttpWebRequest]::Create($Url)
        $request.Method = $Method
        $request.AllowAutoRedirect = $false
        $request.Timeout = 10000
        if ($Method -eq 'POST') {
            $request.ContentType = "application/json"
            $request.ContentLength = 2
            $stream = $request.GetRequestStream()
            $bytes = [System.Text.Encoding]::UTF8.GetBytes('{}')
            $stream.Write($bytes, 0, $bytes.Length)
            $stream.Close()
        }
        $response = $request.GetResponse()
        $statusCode = [int]$response.StatusCode
        $location = $response.Headers['Location']
        $response.Close()
        return @{ Code=$statusCode; Location=$location }
    } catch [System.Net.WebException] {
        if ($_.Exception.Response) {
            $statusCode = [int]$_.Exception.Response.StatusCode
            $location = $_.Exception.Response.Headers['Location']
            return @{ Code=$statusCode; Location=$location }
        }
        return @{ Code=0; Location=$null; Error=$_.Exception.Message }
    } catch {
        return @{ Code=0; Location=$null; Error=$_.Exception.Message }
    }
}

# Execution
Write-Host ""
Write-Host "=========================================================" -ForegroundColor Cyan
Write-Host " ReloadeD Theme - Admin URL Access Test (Wave 9 C)" -ForegroundColor Cyan
Write-Host "=========================================================" -ForegroundColor Cyan
Write-Host " Base URL: $BaseUrl"
Write-Host " Total endpoints: $($endpoints.Count)"
Write-Host ""

$passCount = 0
$failCount = 0
$warnCount = 0

foreach ($ep in $endpoints) {
    $url = $BaseUrl + $ep.Url
    $result = Test-Endpoint -Url $url -Method $ep.Method
    $code = $result.Code
    $location = $result.Location

    if ($ep.Type -eq 'admin') {
        # Admin endpoints must reject. Valid rejections:
        #   - 401 (Unauthorized) / 403 (Forbidden) - explicit auth rejection
        #   - 400 (Bad Request) - WP responds wp_die('0') for admin-post/admin-ajax
        #     actions without nopriv handler (intentional rejection mechanism)
        #   - 3xx redirect AWAY from /wp-admin/ - admin page protection. Default
        #     WP sends anon users to wp-login.php; with the theme's Hide Login
        #     feature ON (Wave 8.5) it redirects to the site home instead, to
        #     avoid leaking the custom login slug. Both are valid: no admin
        #     content is served. We accept any 3xx whose target is NOT itself
        #     under /wp-admin/ (a redirect back into admin wouldn't protect).
        $isProtectiveRedirect = ($code -ge 301 -and $code -le 308) -and $location -and ($location -notmatch '/wp-admin/')
        $isRejected = ($code -eq 400 -or $code -eq 401 -or $code -eq 403)
        if ($isProtectiveRedirect -or $isRejected) {
            $verdict = "PASS"
            $color = "Green"
            $passCount++
        } else {
            $verdict = "FAIL"
            $color = "Red"
            $failCount++
        }
    } else {
        # Public: MUST NOT return 401/403; anything else is fine
        if ($code -eq 401 -or $code -eq 403) {
            $verdict = "FAIL"
            $color = "Red"
            $failCount++
        } elseif ($code -eq 0) {
            $verdict = "WARN"
            $color = "Yellow"
            $warnCount++
        } else {
            $verdict = "PASS"
            $color = "Green"
            $passCount++
        }
    }

    $codeStr = "$code".PadRight(4)
    $typeStr = $ep.Type.PadRight(7).ToUpper()
    Write-Host ("[{0}] {1} {2} {3}" -f $verdict, $codeStr, $typeStr, $ep.Name) -ForegroundColor $color
    if ($location) {
        Write-Host ("           -> $location") -ForegroundColor DarkGray
    }
}

Write-Host ""
Write-Host "=========================================================" -ForegroundColor Cyan
Write-Host (" PASS: {0}   FAIL: {1}   WARN: {2}" -f $passCount, $failCount, $warnCount)
Write-Host "=========================================================" -ForegroundColor Cyan
Write-Host ""

if ($failCount -gt 0) {
    Write-Host "[!] FAIL detected - endpoint accessible without auth where it should reject." -ForegroundColor Red
    exit 1
} elseif ($warnCount -gt 0) {
    Write-Host "[!] WARN detected - endpoint did not respond (timeout/network). Re-run." -ForegroundColor Yellow
    exit 2
} else {
    Write-Host "[OK] All endpoints behaved as expected." -ForegroundColor Green
    exit 0
}
