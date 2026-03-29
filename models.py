from sqlalchemy import Column, Integer, String, Date, ForeignKey, Boolean
from sqlalchemy.orm import relationship
from database import Base

class Project(Base):
    __tablename__ = "projects"

    id = Column(Integer, primary_key=True, index=True)
    name = Column(String, index=True)
    description = Column(String, nullable=True)

    phases = relationship("Phase", back_populates="project", cascade="all, delete-orphan")

class Phase(Base):
    __tablename__ = "phases"

    id = Column(Integer, primary_key=True, index=True)
    project_id = Column(Integer, ForeignKey("projects.id"))
    name = Column(String, index=True)
    start_date = Column(Date)
    end_date = Column(Date)
    color = Column(String, default="#cccccc")
    description = Column(String, nullable=True)
    google_event_id = Column(String, nullable=True)
    depends_on_id = Column(Integer, ForeignKey("phases.id"), nullable=True)

    project = relationship("Project", back_populates="phases")
    milestones = relationship("Milestone", back_populates="phase", cascade="all, delete-orphan")
    events = relationship("Event", back_populates="phase", cascade="all, delete-orphan")
    
    depends_on = relationship("Phase", remote_side=[id], backref="dependents")

class Milestone(Base):
    __tablename__ = "milestones"

    id = Column(Integer, primary_key=True, index=True)
    phase_id = Column(Integer, ForeignKey("phases.id"))
    name = Column(String)
    target_date = Column(Date)
    google_event_id = Column(String, nullable=True)

    phase = relationship("Phase", back_populates="milestones")

class Event(Base):
    __tablename__ = "events"

    id = Column(Integer, primary_key=True, index=True)
    phase_id = Column(Integer, ForeignKey("phases.id"))
    name = Column(String)
    start_date = Column(Date)
    end_date = Column(Date)
    google_event_id = Column(String, nullable=True)

    phase = relationship("Phase", back_populates="events")

class User(Base):
    __tablename__ = "users"

    id = Column(Integer, primary_key=True, index=True)
    username = Column(String, unique=True, index=True)
    hashed_password = Column(String)
