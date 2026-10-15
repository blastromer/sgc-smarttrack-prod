<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Privacy policy — SGC SmartTrack</title>
    <meta name="description" content="How SGC SmartTrack collects, uses, stores, and shares school assessment data and Google Drive files for the DepEd SGC Functionality Assessment.">
    <style>
        body { margin: 0; background: #f3f6fb; color: #172b4d; font: 16px/1.6 "Segoe UI", sans-serif; }
        header, main, footer { max-width: 46rem; margin: 0 auto; padding: 1.25rem 1.25rem 0; }
        header { padding-top: 2rem; }
        h1 { font-size: 1.8rem; line-height: 1.2; margin: 0 0 0.35rem; }
        h2 { font-size: 1.15rem; margin: 1.6rem 0 0.4rem; }
        p, li { margin: 0.4rem 0; }
        a { color: #1d4ed8; }
        .muted { color: #64748b; }
        footer { padding-bottom: 2.5rem; }
    </style>
</head>
<body>
    <header>
        <p class="muted">Schools Division Office of Cadiz City · Department of Education</p>
        <h1>Privacy policy for SGC SmartTrack</h1>
        <p class="muted">Effective 15 October 2026</p>
    </header>
    <main>
        <p>
            SGC SmartTrack is the online tool of the Schools Division Office of Cadiz City for the School Governance Council Functionality Assessment under DepEd Order No. 26, s. 2022.
            This page explains what information the system collects, why it is collected, where it is stored, and who can see it.
        </p>

        <h2>Who this applies to</h2>
        <p>
            This policy applies to School Heads, encoders, Division reviewers, and the Super Admin who use SGC SmartTrack, and to the Google account a school user connects so assessment files can be stored in Google Drive.
        </p>

        <h2>Information we collect</h2>
        <p>We collect only what the assessment needs:</p>
        <ul>
            <li>Account details: name, DepEd email address, role, school name, school ID, position, office, and account status. Passwords are stored as a one-way hash, not as readable text.</li>
            <li>Assessment answers: the Yes or No response for each of the 12 functionality indicators, the School Head quality-assurance certification, and the packet status.</li>
            <li>Means of verification: the files a school uploads for an indicator, including the file name, type, and size. Accepted types are PDF, Word, and images.</li>
            <li>Review records: whether Division accepted or returned a file, and the reason given when a file is returned.</li>
        </ul>

        <h2>Google account and Google Drive</h2>
        <p>
            A school user may connect one Google account from the MOV templates page. The connection asks for permission to see, create, and edit files in that Google Drive
            (<span>https://www.googleapis.com/auth/drive</span>).
            SGC SmartTrack uses that permission only to create the school’s year and school folders inside the official SGC MOV folders, to upload the school’s assessment files into those folders, to open those files for Division review, and to delete a Drive copy when the school removes or replaces that file.
        </p>
        <p>
            We store a Google refresh token on the application server so the school does not have to sign in to Google on every upload. We do not use Google user data for advertising. We do not sell it. We do not use it to train general artificial-intelligence models. We do not read Drive files that are unrelated to this assessment.
        </p>
        <p>
            A file sent to Division may be shared so that anyone with the link can view it. That lets a Division reviewer open the file in Google Drive when it cannot be shown inside SGC SmartTrack. The link is available to signed-in Division reviewers of this system.
        </p>

        <h2>How we use the information</h2>
        <ul>
            <li>To record the school’s self-assessment and the files that support each indicator.</li>
            <li>To let the Division accept or return those files and record whether the School Governance Council is functional.</li>
            <li>To keep a copy of submitted files in the school’s Google Drive folder instead of keeping those files on the application server.</li>
            <li>To send in-app notices to the school and to Division reviewers about submission, return, and removal requests.</li>
        </ul>

        <h2>Who can see it</h2>
        <ul>
            <li>The School Head and encoder of that school can see that school’s answers and files.</li>
            <li>Division reviewers of the Schools Division Office of Cadiz City can see submitted packets and open the files for validation.</li>
            <li>The Super Admin can manage users, cycles, and the system.</li>
            <li>Google receives the files and folder names that the connected Google account stores in Drive, under Google’s own terms.</li>
        </ul>
        <p>We do not give this information to advertisers or to other organizations for their own marketing.</p>

        <h2>Where it is stored and for how long</h2>
        <p>
            Account and assessment records are stored on the SGC SmartTrack server. While a school is still preparing a file, that file may stay on the server. After a successful submit, the file is copied to the school’s Google Drive folder and removed from the server.
            The Google refresh token stays until that user chooses Disconnect Drive. Disconnecting stops future Drive access. It does not delete files already saved in Drive.
        </p>
        <p>
            Records are kept for the assessment cycle and for the Division’s SGC monitoring. A School Head can remove a file that Division has not yet accepted. To ask for an account or a Google connection to be removed, contact the Division SGC Focal at the Schools Division Office of Cadiz City.
        </p>

        <h2>Security</h2>
        <p>
            Sign-in is required for assessment pages. Division and school users see only the packets their role allows. The connection to this site uses HTTPS. Google access uses the official Google sign-in screen, and the refresh token is kept on the server rather than in the browser.
        </p>

        <h2>Changes</h2>
        <p>
            If this policy changes, the updated page will be published at this same address with a new effective date.
        </p>

        <h2>Contact</h2>
        <p>
            Schools Division Office of Cadiz City, Department of Education. Ask the Division SGC Focal who administers SGC SmartTrack about this policy, your account, or your Google Drive connection.
        </p>
    </main>
    <footer>
        <p><a href="{{ url('/') }}">Back to SGC SmartTrack</a></p>
    </footer>
</body>
</html>
