# This repository has been moved to https://codeberg.org/Robert1976/teamsgovernance

# Teams Governance

Teams Governance adds an admin page that lists user-created Nextcloud Teams (formerly Circles).

## Installation

This app can be installed to Nextcloud version 31 or higher. The app has been tested on the Docker AIO version of Nextcloud. To install this app:

Copy the teamsgovernance-1.0.0-prebuilt.tar.gz from the dist folder to the custom_apps volume of your nextcloud_aio_nextcloud container.

E.g.: '/var/lib/docker/volumes/nextcloud_aio_nextcloud/_data/custom_apps'
Untar the file: tar -xzf teamsgovernance-1.0.0-prebuilt.tar.gz
Remove the tar file: teamsgovernance-1.0.0-prebuilt.tar.gz

Go to your Nextcloud instance and activate the app.

## Usage

1. Enable the 'teamsgovernance' app.
2. Open **Administration settings**.
3. Click **Teams Governance** in the admin settings menu.

The page shows a paginated table with:

- Team name
- Creator
- Creation date
- Member count
- Per-team details panel with connected resources

Use the search box to filter by team name, creator display name, or creator username.

Use **View details** on a row to load connected Team resources on demand. The details panel includes:

- Team share (Files) yes/no indicator
- Connected resource count
- Provider breakdown (for example Talk, Deck, Files)
- Resource links and labels

If Teams/Circles data is unavailable, the page shows a clear status message instead of failing.

Screenshot:
<img width="1918" height="995" alt="Teams-Governance" src="https://github.com/user-attachments/assets/05ad3a66-a834-478a-9c99-d84fc1c2b1b0" />
