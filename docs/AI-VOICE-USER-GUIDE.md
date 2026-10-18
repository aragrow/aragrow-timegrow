# AI Voice User Guide

Oct 17, 2026 · @David Arago

The voice assistant lets you record your time and ask about your work just by talking to TimeGrow, the way you would talk to a colleague. Nothing is saved until you confirm.

## Start a conversation

1. Click the round purple **microphone icon** in the bottom-right corner of any TimeGrow admin page. A chat opens above it.
2. Tap the small **mic** button in the chat and talk, or type in the box and press Enter.
3. Answer its questions the same way, or tap one of the buttons it shows.
4. Click the microphone icon again, the **×**, or press Esc to close it.

The assistant remembers the conversation while the chat is open. Closing it, or reloading the page, starts a new conversation next time.

If you don't see the microphone icon, ask your administrator to turn on AI Voice for you.

## Its name and voice

The assistant is called **Sam Spade** unless your administrator renamed it (Settings > AI Voice Entry > Assistant name). You can call it by name: "Sam, how many hours this week?" works the same as the question on its own.

It speaks with the voice your administrator chose, or the most natural voice your device has. To use a different one on your device, click **Voice** at the top of the chat and pick one; voices marked ★ sound the most natural. Your pick is remembered on that device and wins over the site's choice.

## Record your time

Say what you did in one sentence. The assistant fills in what it understood and only asks for what is missing: client, project, day, hours, billable and what you worked on. Because you give the hours, it doesn't ask for a start time. If you say one ("from 9 to 11"), it keeps it.

| You say | It understands |
| --- | --- |
| "Two hours yesterday on the SEO audit for Copperleaf, keyword research" | Client, project, day, hours and description; asks the rest |
| "Three and a half hours today on the website redesign, billable" | Hours 3.5, today, billable |
| "From 9 to 11:30 on the audit" | 2.5 hours, started at 9:00 |
| "45 minutes on support for Birchway" | 0.75 hours |
| "Last Friday" (when asked the day) | The most recent Friday |
| "Yes" / "No" (when asked if it's billable) | Billable or not billable |
| "The second one" (after it lists projects) | The second project on the list |

It won't guess a vague amount like "the morning" or "two or three hours"; it asks how long. When everything is there, it reads back a summary such as *"Copperleaf, SEO Audit. Thu, Oct 15, 2h 30m, billable. Keyword research."* Say **save it** or tap **Save entry**. Nothing is saved before that.

You only see your own projects. If a client has several projects with the same name, or several clients have similar names, it asks which one you mean.

## Punch in and out

Say **punch in** or **clock in** when you start, and **punch out** or **clock out** when you stop.

- **Punching in:** it asks for the client and project (unless you said them, like "punch in on the SEO audit"), then what time you started. Say **now** or a time such as "9:30".
- **Punching out:** it asks what time you stopped, and reminds you when you clocked in. Say **now** or a time.
- It reads back what it will do, such as *"Clock in on SEO Audit for Copperleaf at 9:30 AM?"*. Nothing is saved until you say **yes** or tap the button.

It won't clock you in twice, or out when you aren't clocked in. A time later than now, or a stop time before you clocked in, is refused, and it asks again.

## Fix mistakes

Correct it the way you'd correct a person. You can do this at any point, including at the summary.

| You say | What happens |
| --- | --- |
| "Actually make it three hours" | Only the hours change; it shows the summary again |
| "No, Birchway" | The client changes; it asks for the project again if needed |
| "Yes, but make it billable" | Billable changes; it does not save yet |
| "Change something" or "That's wrong" | It asks which part, then asks for it again |
| "Start over" | A new, empty entry |
| "Never mind" or "Cancel" | The entry is dropped; nothing is saved |

A plain "no" at the summary means you want to change something, not cancel.

## Ask questions

Ask about your own time and projects at any moment, even in the middle of recording an entry. The assistant answers, then picks the entry up where you left off.

| Ask | You get |
| --- | --- |
| "What did I log last?" | Your last entry |
| "What did I work on for Copperleaf?" | Your recent entries for that client |
| "How many hours this week?" | Your total, by project |
| "And last week?" | The same question for last week |
| "My clients" | The clients you work for |
| "Projects for Birchway" | That client's projects you can use |
| "Newest projects" | The latest projects |
| "Am I clocked in?" | Your clock status |

Periods it knows: today, yesterday, this week, last week, this month, last month, this year and last year.

The assistant only looks at time entries and invoices from the last 3 months. If you ask for more, it tells you:

- **Partly older** ("how much did we bill this year"): you get the last 3 months, a note saying so, and links to the reports for the full period.
- **All older** ("give me last year's total invoiced"): it says it can't answer and links the reports that cover any period, such as the Client Activity Report or Yearly Tax Report.

Expenses are not limited.

Administrators can also ask about the whole team, unbilled hours, unpaid invoices and expenses, and can add clients and projects by voice ("Add a client called Jane Doe"). If you ask for something only administrators can see, it tells you so.

## Ask how to use TimeGrow

Ask it how things work: "How do I upload a receipt?", "What is the unbilled report?", "Where do I put my AI key?" or "What can you do?". It answers from TimeGrow's own help and links the page. You only get help for what your role can do; for administrator tasks it tells you to ask your administrator.

## Tips for the best results

- **Say the client and project by name.** "The audit for Copperleaf" is clearer than "the usual project".
- **Give a number for the time.** "Two and a half hours" or "from 9 to 11:30", not "most of the morning".
- **Describe the work in your own words.** That text goes on the invoice for the client, so make the last entry of the day a good summary.
- **One entry at a time.** Record each project separately.
- **Use a quiet place,** and wait for the mic button to turn red before you speak.
- **Pick a natural voice.** Click **Voice** at the top of the chat, choose one marked ★ and click **Try it**. Edge has the most natural voices; on a Mac you can download better ones under System Settings > Accessibility > Spoken Content > System Voice > Manage Voices.
- **Turn the sound off** with **Sound off** at the top of the chat if you prefer to read. Your choice is remembered on your device.
- **Use Chrome, Edge or Safari** to talk. In Firefox, type instead.

## What it won't do, and what is saved

The assistant only does what you can already do in TimeGrow, one thing at a time:

- It never saves without your **Save entry**.
- It can't change or delete entries you already saved, or change many at once. Use the Time Entries page for that.
- It only uses your own projects, and can't record time for someone else.
- It can't show you information your role doesn't allow.

**What is sent to the AI:** what you say or type, the last few messages of the conversation, and your own projects and their clients. The answers to your questions (hours, entries) are looked up by TimeGrow and are not sent to the AI.

**What is saved:** when your administrator has turned it on, the chat shows *"Conversations are saved so administrators can review how the assistant works."* Your administrators can then read the conversation to improve the assistant. Saved conversations are deleted after the number of days your administrator chose (90 by default).

## If something goes wrong

| Problem | What to do |
| --- | --- |
| No microphone icon | Ask your administrator to turn on AI Voice for you |
| No mic button inside the chat | Your browser can't listen (Firefox), so type instead |
| "Microphone access is blocked" | Allow the microphone for this site in your browser, then tap the mic again |
| "I didn't hear anything" | Tap the mic and speak after it turns red |
| It asks every question one by one | The AI is off or not working right now; answer the questions, it still saves normally |
| "Sorry, I didn't get the hours" | Say it as a number: "two hours", "45 minutes" |
| It picked the wrong client or project | Say "No, " and the right name, or "Change something" |
| "That information is only available to administrators" | The question is for administrators only |
| "I can only record or add one thing at a time" | Changes to saved entries are made on the Time Entries page |
| "That wasn't saved" with a reason | Fix what it says (for example a date in the future), then save again |
| The voice sounds robotic | Click Voice and pick one marked ★ |
