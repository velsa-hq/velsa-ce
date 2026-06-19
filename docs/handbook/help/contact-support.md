---
title: Contact support
section: Help
order: 91
surfaces:
  - /support (pages/support/create)
  - /admin/support-requests (pages/admin/support-requests/index)
tour_ids:
  - support-form
  - support-submit
  - support-admin-list
---

When something isn't working or you have a question the handbook doesn't
answer, you can send a **support request** from inside the app - no need
to find an email address or leave what you were doing.

:::video help-and-whats-new

## Sending a request

Open the user menu (your name, top-right) and choose **Get help**, or go
to `/support`. The form asks for:

- a **category** (Question, Problem, or Suggestion),
- a short **subject**, and
- a **description** of what you need.

To save you retyping, each request automatically carries the context the
support team needs to help: who you are, the page you were on when you
opened the form, and the application version. You don't see or manage any
of that - it travels with the request.

Submit, and you'll get a confirmation. If the deployment has support
notifications configured, the request is also emailed to the support
contacts immediately.

## What happens next

Every request is recorded so nothing is lost if email is down. An
administrator reviews requests at **Admin -> Support requests**
(`/admin/support-requests`), where each one shows its category, subject,
who sent it, the captured context, and a status of **Open** or
**Closed**. Admins mark a request **Closed** once it's handled.

## Setting up notifications (administrators)

Two settings under **Admin -> System settings -> Support** control email
delivery:

- **Support request notifications** - turn email delivery on or off.
- **Support request recipients** - a comma-separated list of the
  addresses that receive new requests. If this is empty, no email is
  sent, but requests are still recorded for review in the admin list.

Recording always happens; email is the optional convenience layer on top.
