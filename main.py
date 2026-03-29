from fastapi import FastAPI, Depends, Request, HTTPException, status
from fastapi.staticfiles import StaticFiles
from fastapi.templating import Jinja2Templates
from sqlalchemy.orm import Session
import os

from database import engine, Base, get_db
import models, schemas
from sync import sync_service

# Create tables
models.Base.metadata.create_all(bind=engine)

app = FastAPI()

# Make sure templates directory exists
os.makedirs("templates", exist_ok=True)
templates = Jinja2Templates(directory="templates")

@app.get("/")
def read_root(request: Request):
    return templates.TemplateResponse("index.html", {"request": request})

@app.get("/login")
def login_page(request: Request):
    return templates.TemplateResponse("login.html", {"request": request})

# API Endpoints
@app.post("/api/projects/", response_model=schemas.Project)
def create_project(project: schemas.ProjectCreate, db: Session = Depends(get_db)):
    db_project = models.Project(**project.model_dump())
    db.add(db_project)
    db.commit()
    db.refresh(db_project)
    return db_project

@app.get("/api/projects/", response_model=list[schemas.Project])
def get_projects(skip: int = 0, limit: int = 100, db: Session = Depends(get_db)):
    return db.query(models.Project).offset(skip).limit(limit).all()

@app.post("/api/phases/", response_model=schemas.Phase)
def create_phase(phase: schemas.PhaseCreate, project_id: int, db: Session = Depends(get_db)):
    event_id = sync_service.sync_event(phase.start_date, phase.end_date, phase.name)
    
    db_phase = models.Phase(**phase.model_dump(), project_id=project_id, google_event_id=event_id)
    db.add(db_phase)
    db.commit()
    db.refresh(db_phase)
    return db_phase

@app.delete("/api/phases/{phase_id}")
def delete_phase(phase_id: int, db: Session = Depends(get_db)):
    phase = db.query(models.Phase).filter(models.Phase.id == phase_id).first()
    if phase:
        if phase.google_event_id:
            sync_service.delete_event(phase.google_event_id)
        db.delete(phase)
        db.commit()
    return {"ok": True}

from datetime import timedelta

@app.put("/api/phases/{phase_id}", response_model=schemas.Phase)
def update_phase(phase_id: int, phase_in: schemas.PhaseCreate, db: Session = Depends(get_db)):
    db_phase = db.query(models.Phase).filter(models.Phase.id == phase_id).first()
    if not db_phase:
        raise HTTPException(status_code=404, detail="Phase not found")

    # Calculate offset
    delta_days = (phase_in.start_date - db_phase.start_date).days

    db_phase.name = phase_in.name
    db_phase.start_date = phase_in.start_date
    db_phase.end_date = phase_in.end_date
    db_phase.color = phase_in.color
    db_phase.depends_on_id = phase_in.depends_on_id

    # Update Google Event
    if db_phase.google_event_id:
        sync_service.sync_event(db_phase.start_date, db_phase.end_date, db_phase.name, color_id=None, previous_event_id=db_phase.google_event_id)

    # Cascade to dependents recursively
    def shift_dependents(phase_obj, offset):
        if offset == 0: return
        for dependent in phase_obj.dependents:
            dependent.start_date += timedelta(days=offset)
            dependent.end_date += timedelta(days=offset)
            if dependent.google_event_id:
                sync_service.sync_event(dependent.start_date, dependent.end_date, dependent.name, color_id=None, previous_event_id=dependent.google_event_id)
            shift_dependents(dependent, offset)

    shift_dependents(db_phase, delta_days)
    
    db.commit()
    db.refresh(db_phase)
    return db_phase


