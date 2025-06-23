# DecepTest

DecepTest is a powerful PHP/MySQL application designed for Managed Service Providers (MSPs), internal IT departments, and business owners to simulate phishing attacks and assess organizational vulnerability. Create realistic email campaigns using templates such as SharePoint, DocuSign, OneDrive, and Google, send them to targeted users, and receive detailed reports on who interacts with phishing links.

---

## Features

- **Phishing Campaign Creation:** Easily craft campaigns using built-in templates for popular services.
- **Customizable Templates:** Modify or add templates to suit your organization's needs.
- **Automated Email Delivery:** Send campaigns to selected recipients using PHPMailer.
- **Click Tracking & Reporting:** Monitor who clicks on phishing links and generate comprehensive reports.
- **Multi-Tenant Support:** Designed for MSPs managing multiple clients, but flexible enough for single organizations.

---

## Requirements

- **PHP** (version 7.4 or higher recommended)
- **MySQL** (5.7 or higher)
- **PHPMailer** (latest stable version)

---

## Installation

1. **Clone or Download** this repository to your web server.
2. **Configure Database:** Import the provided SQL schema and update `config.php` with your MySQL credentials.
3. **Install PHPMailer:** Place the PHPMailer library in the `/vendor` directory or install via Composer.
4. **Set Permissions:** Ensure the web server user has read/write access to the necessary directories.
5. **Configure Web Server:** Point your web root to the `/public` directory (if applicable).

---

## Usage

### Step 1: [Your Step 1 Title Here]
_Describe the first step here (e.g., logging in, setting up users, etc.)._

### Step 2: [Your Step 2 Title Here]
_Describe the second step here (e.g., creating a new campaign, selecting a template, etc.)._

### Step 3: [Your Step 3 Title Here]
_Describe the third step here (e.g., sending emails, viewing reports, etc.)._

---

## Example Templates

- **Microsoft**
- **SharePoint**
- **OneDrive**
- **Google**
- **Google Drive**
- **DocuSign**
  
You can modify existing templates or add your own to match the services your users commonly interact with.

---

## Reporting

DecepTest tracks every recipient who clicks on a phishing link, providing you with detailed analytics and exportable reports to identify at-risk users and plan targeted training.

---

## Support

For issues, feature requests, or contributions, please open an issue or submit a pull request.

---

## Disclaimer

DecepTest is intended for ethical use within organizations to improve security awareness and should only be used with proper authorization.

---

**Current Version:** 1.0  
**Last Updated:** June 18, 2025
