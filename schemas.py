from pydantic import BaseModel
from typing import List, Optional
from datetime import date

class EventBase(BaseModel):
    name: str
    start_date: date
    end_date: date

class EventCreate(EventBase):
    pass

class Event(EventBase):
    id: int
    phase_id: int
    google_event_id: Optional[str] = None

    class Config:
        from_attributes = True

class MilestoneBase(BaseModel):
    name: str
    target_date: date

class MilestoneCreate(MilestoneBase):
    pass

class Milestone(MilestoneBase):
    id: int
    phase_id: int
    google_event_id: Optional[str] = None

    class Config:
        from_attributes = True

class PhaseBase(BaseModel):
    name: str
    start_date: date
    end_date: date
    color: Optional[str] = "#cccccc"
    description: Optional[str] = None
    depends_on_id: Optional[int] = None

class PhaseCreate(PhaseBase):
    pass

class Phase(PhaseBase):
    id: int
    project_id: int
    google_event_id: Optional[str] = None
    milestones: List[Milestone] = []
    events: List[Event] = []

    class Config:
        from_attributes = True

class ProjectBase(BaseModel):
    name: str
    description: Optional[str] = None

class ProjectCreate(ProjectBase):
    pass

class Project(ProjectBase):
    id: int
    phases: List[Phase] = []

    class Config:
        from_attributes = True
