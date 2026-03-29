import os
from google.oauth2.credentials import Credentials
from google_auth_oauthlib.flow import InstalledAppFlow
from google.auth.transport.requests import Request
from googleapiclient.discovery import build

SCOPES = ['https://www.googleapis.com/auth/calendar.events']

class CalendarSyncService:
    def __init__(self):
        self.creds = None
        # Provide real creds via credentials.json later.
        if os.path.exists('token.json'):
            self.creds = Credentials.from_authorized_user_file('token.json', SCOPES)
        self.service = build('calendar', 'v3', credentials=self.creds) if self.creds else None
        
    def sync_event(self, start_date, end_date, summary, description='', color_id=None, previous_event_id=None):
        if not self.service:
            print(f"Mock Sync: {summary} from {start_date} to {end_date}")
            # Mock return ID
            return "mock_event_id_" + summary.replace(" ", "_")
            
        event = {
            'summary': summary,
            'description': description,
            'start': {
                'date': start_date.isoformat(),
            },
            'end': {
                'date': end_date.isoformat(),
            },
        }
        if color_id:
             event['colorId'] = color_id

        if previous_event_id:
            try:
                updated_event = self.service.events().update(calendarId='primary', eventId=previous_event_id, body=event).execute()
                return updated_event['id']
            except Exception as e:
                print(f"Update failed, creating new. Error: {e}")
        
        created_event = self.service.events().insert(calendarId='primary', body=event).execute()
        return created_event['id']

    def delete_event(self, event_id):
        if not self.service:
            print(f"Mock Delete: {event_id}")
            return
        try:
            self.service.events().delete(calendarId='primary', eventId=event_id).execute()
        except Exception as e:
            print(f"Delete failed: {e}")

sync_service = CalendarSyncService()
