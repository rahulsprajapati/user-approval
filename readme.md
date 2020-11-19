User Approval
======================

[![Travis](https://travis-ci.org/rahulsprajapati/user-approval.svg?branch=master)](https://travis-ci.org/rahulsprajapati/user-approval/) [![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/rahulsprajapati/user-approval/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/rahulsprajapati/user-approval/?branch=master) [![Code Coverage](https://scrutinizer-ci.com/g/rahulsprajapati/user-approval/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/rahulsprajapati/user-approval/?branch=master)

Approval/block user account for new registered users based on default user role.

### WorkFlow

- Enable User Approve Plugin.
- Enable User registration with default User role from WP from General -> Settings.

  ![Enable Registration](./screenshots/Screenshot-1.png)`

- Register user on WordPress will now see a message to wait for apporval.

  ![User Registration](./screenshots/Screenshot-2.png)`

  ![Registration Message](./screenshots/Screenshot-3.png)`

- Admin will now get a mail for registered user.

  ![New User Mail](./screenshots/Screenshot-4.png)`

- Admin will see new user detail in users list screen with Approve/Block Action link and current status as pending.

  ![User list screen](./screenshots/Screenshot-5.png)`

- Until new user account gets approve by admin, user won't be able to login or generate password.

  ![Lost Password Screen](./screenshots/Screenshot-6.png)`

- Once admin approve or block user account, user will get a mail with respective action message.

  ![Account Block Mail](./screenshots/Screenshot-7.png)`

- User will get a password reset/generate link once account is approved, and now they can login normally.

  ![Approved Account Mail](./screenshots/Screenshot-8.png)`


#### Filters:

- `user_approval_registered_user_message`: Update message shown on user registration form.
- `user_approval_new_user_admin_email_data`: Update email subject + body message for new registered user.
- `user_approval_approved_user_email_data`: Update email subject + body message for mail sent to user on account approval.
- `user_approval_blocked_user_email_data`: Update email subject + body message for mail sent to user on account blocked.
- `user_approval_default_user_role`: Update default user role.
