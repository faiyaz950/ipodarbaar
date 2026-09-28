<p>Once an IPO closes, the next question is simple: did you get shares? Under SEBI's T+3 timeline, allotment is finalised one working day after the issue closes, and you can check the result online in a minute using your <strong>PAN</strong>, <strong>application number</strong> or <strong>demat account ID</strong>. For IPOs that are awaiting allotment right now, see the <a href="{{ route('ipos.allotment') }}">IPO allotment status</a> page with direct registrar links.</p>

<h2 id="when">When is the allotment status available?</h2>
<p>The basis of allotment is finalised on <strong>T+1</strong> (T is the closing day). Registrars usually publish results on the evening of that day, and BSE and NSE update their pages around the same time. Shares reach allottees' demat accounts on T+2 and listing happens on T+3.</p>

<h2 id="registrar">Method 1: on the registrar's website</h2>
<p>Every IPO appoints a registrar (RTA) that processes applications and runs the allotment. The registrar is named in the prospectus and on each IPO page on IPO Darbaar.</p>
<ol>
    <li>Open the registrar's IPO allotment page.</li>
    <li>Select the IPO from the dropdown (it appears once allotment is finalised).</li>
    <li>Choose PAN, application number or DP/client ID, enter it and complete the captcha.</li>
    <li>Submit to see the number of shares applied for and allotted.</li>
</ol>
<table>
    <thead><tr><th>Registrar</th><th>Allotment status page</th></tr></thead>
    <tbody>
        @foreach (config('ipodarbar.registrars') as $registrar)
            <tr><td>{{ $registrar['name'] }}</td><td><a href="{{ $registrar['url'] }}" target="_blank" rel="noopener nofollow">Check on {{ $registrar['name'] }}</a></td></tr>
        @endforeach
    </tbody>
</table>

<h2 id="bse">Method 2: on the BSE website</h2>
<ol>
    <li>Open BSE's <a href="https://www.bseindia.com/investors/appli_check" target="_blank" rel="noopener nofollow">application status page</a>.</li>
    <li>Select issue type <strong>Equity</strong> and choose the IPO name.</li>
    <li>Enter your application number or PAN, verify the captcha and search.</li>
</ol>

<h2 id="nse">Method 3: on the NSE website</h2>
<p>NSE's <a href="https://www.nseindia.com/invest/check-trades-bids-verify-ipo-bids" target="_blank" rel="noopener nofollow">bid verification page</a> lets you check bids and allotment for IPOs listed on NSE after a quick sign-up with your PAN.</p>

<h2 id="other-ways">Other ways to know</h2>
<ul>
    <li><strong>Bank or UPI app:</strong> if the blocked amount is debited, you have been allotted; if it is released, you have not.</li>
    <li><strong>Depository SMS/email:</strong> CDSL or NSDL notifies you when shares are credited to your demat account.</li>
    <li><strong>Broker app:</strong> most brokers update the IPO order status after allotment.</li>
</ul>

<h2 id="what-next">Allotted or not: what happens next</h2>
<p><strong>If you are allotted</strong>, the shares appear in your demat holdings on T+2 and can be sold from 10 AM on the listing day. <strong>If you are not allotted</strong>, the blocked money is released automatically on T+2. There is nothing to claim. In hot IPOs most retail applicants miss out because allotment is decided by lottery; our guide on <a href="{{ route('guides.show', 'how-ipo-allotment-works') }}">how IPO allotment works</a> explains why.</p>
