# Student usability and visual review

The app was exercised in installed Google Chrome with the real Express API, MongoDB, and Socket.IO. Browser clicks, typing, file selection, and downloads were tested, and actual rendered screenshots were inspected. All accounts and conversations used fictional demo data.

## Screens and layouts

Desktop and laptop layouts were reviewed at 1440 and 1280 pixels wide, with additional tablet/laptop checks at 768 and 1024 pixels. Phone layouts were checked at 320 and 390 pixels, including a reduced-height chat viewport to check that the message composer remains reachable.

The main walkthrough captured 31 rendered views and checked for horizontal overflow and readable inputs. It reported zero browser page errors. Follow-up checks covered incoming friend requests, profile persistence, keyboard focus, and conversation headings.

| Screen                   | Screenshot                                                            |
| ------------------------ | --------------------------------------------------------------------- |
| Sign in                  | [Desktop](images/login.png)                                           |
| Home and class shortcuts | [Desktop](images/dashboard.png), [phone](images/mobile-dashboard.png) |
| Messages                 | [Desktop](images/messages.png), [phone](images/mobile-chat.png)       |
| Classmates and friends   | [Desktop](images/people.png)                                          |
| Campus rooms             | [Desktop](images/discover.png)                                        |
| Navigation               | [Phone](images/mobile-navigation.png)                                 |

These screenshots show the final production build on a fresh fictional campus. The checks use Chrome viewport simulations; physical devices, Safari, and Firefox were not tested.

## Student journeys

- Register a student and verify the correct class group appears automatically.
- Open class chat from the home page and send a message.
- Find a classmate by full name, accept an incoming friend request, and message the new friend.
- Join a campus room and inspect its members and leave confirmation.
- Create a private study group from the home shortcut and invite accepted friends.
- Upload revision notes, download the file through the browser, and confirm it survives a reload.
- Exchange live messages between two student accounts, confirm anonymous sender redaction, and report a message for faculty review.
- Save a profile, reload it, switch themes, and navigate between chat and the conversation list on a phone.
- Exercise faculty moderation and administrative section management.

## Changes prompted by the review

- Class chat, classmates, and study-group creation have clear home shortcuts. Class groups appear first in home community cards.
- Text stays readable across breakpoints; form and chat inputs use 16 px type. Primary phone controls have larger touch targets.
- Joined community cards show an explicit **Open chat** action.
- Creating from My groups or the study shortcut defaults to a private group, with friend invitations.
- Phone navigation includes a visible close button, keyboard dismissal, focus containment, and an accessible sign-out action.
- Live message updates preserve focus and draft text while a student names a study group.
- Escape dismisses the top confirmation and restores focus to the underlying dialog.
- A chat retains its resolved title and member information while conversation data refreshes.

## Verification

The React production build succeeded. All 14 MongoDB integration tests and all 5 browser workflows passed. The browser suite includes a regression check for a live message arriving during study-group creation, nested dialog dismissal, and preserving a new chat's heading after sending.

To run the maintained checks:

```sh
npm run build
npm test
# Set PLAYWRIGHT_CHANNEL=chrome to use installed Chrome.
npm run test:e2e
npm run format:check
```
