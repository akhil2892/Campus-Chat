# PHP to MERN migration

The original XAMPP application is preserved in `legacy/php` for reference. Original GitHub web technology exercises are retained in `coursework`. Personal uploaded images remain only in the local ignored `.legacy-uploads` directory. The existing GitHub commits are preserved; migration commits use their actual creation dates.

## Feature inventory

| Existing PHP behavior | MERN replacement |
| --- | --- |
| Registration, login, logout, student / lecturer / admin roles | Express authentication, MongoDB sessions, protected React routes |
| College email, roll number, section and year validation | Server validation, configurable college domains |
| Automatic section group on registration | Atomic section conversation upsert and membership assignment |
| Friend search, requests, acceptance and rejection | People directory and friendship API |
| Friends-only direct conversations | Authorized direct conversation API |
| Section, interest and general groups; create / join / leave / delete | Conversation model, membership and creator controls |
| Campus-wide room discovery, join and leave | Discover page and room membership API |
| Chat messages and image / document uploads | Persistent messages, authenticated attachment downloads |
| Anonymous messaging | Recipient-specific serialization hides the sender from peers |
| Polling-based typing and message refresh | Authenticated Socket.IO, live typing and updates |
| Message read tracking | Per-conversation read cursors and receipt counts |
| Profile names and avatar uploads | Profile editor and validated image upload |
| Message reporting to a chosen faculty member | Report submission and assigned moderation queue |
| Admin statistics, sections, activation and audit logs | Admin overview, section editor and audit trail |

## Implementation

`client` is a React application built with Vite. `server` provides Express routes, Mongoose models and Socket.IO. The app uses real MongoDB in every mode. A local demo command starts an isolated MongoDB instance and seeds fictional accounts; normal development uses a configured MongoDB instance or Atlas. Production serves the React build from Express on one origin.

Messages and attachments require conversation membership. Section groups additionally require matching section and year (or faculty/admin privileges). Anonymous peer messages omit the real sender ID, name and avatar; assigned faculty and admins can identify the sender when reviewing a report. Demo accounts and seeding are explicit development tools.

The PHP registration screen mentioned email verification but did not send verification email. The migration does not claim delivery or verification. Email domains are validated, and no external mail service is required.

No existing MySQL records are imported, as requested. Demo records are fictional.
