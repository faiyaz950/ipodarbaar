<p>Applying for an IPO in India takes less than five minutes once your accounts are set up. You never pay upfront: the bid amount is only <strong>blocked</strong> in your bank account and is debited only if you get shares. This guide walks through both ways to apply, with UPI through your broker and with ASBA through net banking, plus the mistakes that get applications rejected.</p>

<h2 id="before-you-apply">What you need before applying</h2>
<ul>
    <li><strong>A demat account</strong>, where allotted shares are credited, usually with a trading account at the same broker.</li>
    <li><strong>A PAN</strong> linked to the demat account. Only one application per PAN is allowed in each category.</li>
    <li><strong>A UPI ID</strong> linked to a bank account in your own name (for UPI bids), or <strong>net banking</strong> with an ASBA-enabled bank.</li>
    <li><strong>Enough balance</strong> to cover the full bid amount at the upper price band until allotment.</li>
</ul>

<h2 id="apply-with-upi">Apply for an IPO with UPI (broker app)</h2>
<ol>
    <li>Open the IPO section of your broker app and select the issue from the list of <a href="{{ route('ipos.current') }}">current IPOs</a>.</li>
    <li>Choose your investor category: individual (retail) for bids up to ₹2 lakh, or HNI/NII for larger bids.</li>
    <li>Enter the number of lots. Retail investors should tick <strong>cut-off price</strong> so the bid is valid at whatever final price is fixed within the band.</li>
    <li>Enter your UPI ID and submit the bid.</li>
    <li>Open your UPI app and <strong>approve the mandate request</strong>. The bid counts only after this approval, and it must happen before 5 PM on the closing day.</li>
</ol>
<p class="callout">UPI can be used for bids up to ₹5 lakh. Anything above that must go through ASBA net banking.</p>

<h2 id="apply-with-asba">Apply through net banking (ASBA)</h2>
<ol>
    <li>Log in to net banking and open the IPO / ASBA section (often under "Investments" or "e-Services").</li>
    <li>Select the IPO, enter your demat account (DP ID and client ID), PAN and number of lots.</li>
    <li>Confirm the bid. The bank blocks the amount in your account immediately; there is no separate mandate to approve.</li>
</ol>

<h2 id="how-many-lots">How many lots should you apply for?</h2>
<p>An IPO is sold in fixed <strong>lots</strong>. For mainboard IPOs, SEBI requires one lot to be worth ₹10,000–15,000. In an oversubscribed issue, each successful retail applicant usually gets only one lot through a lottery, so applying for more lots in the retail category does not improve your chance of allotment. The <a href="{{ route('calculators.show', 'ipo-application') }}">IPO application calculator</a> shows the amount to block and your investor category.</p>
<table>
    <thead><tr><th>Category</th><th>Application size</th></tr></thead>
    <tbody>
        <tr><td>Retail individual investor (RII)</td><td>Up to ₹2 lakh</td></tr>
        <tr><td>Small NII (sNII / small HNI)</td><td>Above ₹2 lakh up to ₹10 lakh</td></tr>
        <tr><td>Big NII (bNII / big HNI)</td><td>Above ₹10 lakh</td></tr>
        <tr><td>SME IPO individual investors</td><td>Minimum two lots</td></tr>
    </tbody>
</table>

<h2 id="after-you-apply">What happens after you apply</h2>
<p>SEBI's T+3 timeline, where T is the issue closing day, sets the schedule:</p>
<ul>
    <li><strong>T+1:</strong> basis of allotment is finalised. <a href="{{ route('guides.show', 'how-to-check-ipo-allotment-status') }}">Check your allotment status</a> by PAN.</li>
    <li><strong>T+2:</strong> shares are credited to allottees' demat accounts and blocked money for unallotted bids is released.</li>
    <li><strong>T+3:</strong> the shares list on the stock exchange and trading starts at 10 AM.</li>
</ul>

<h2 id="common-mistakes">Common mistakes that get bids rejected</h2>
<ul>
    <li>Not approving the UPI mandate before 5 PM on the closing day.</li>
    <li>Applying twice with the same PAN, including through two different brokers.</li>
    <li>Using a UPI ID of a bank account in someone else's name.</li>
    <li>Bidding below the final price instead of at cut-off.</li>
    <li>Not keeping enough balance for the amount to stay blocked.</li>
</ul>
<p>Before you apply, compare the <a href="{{ route('ipos.gmp') }}">latest IPO GMP</a> with the company's financials and subscription numbers. Grey market premium is a sentiment signal, not a promise.</p>
