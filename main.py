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
    return templates.TemplateResponse(request=request, name="index.html", context={})

@app.get("/login")
def login_page(request: Request):
    return templates.TemplateResponse(request=request, name="login.html", context={})

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

@app.get("/project/{project_id}")
def project_page(request: Request, project_id: int):
    return templates.TemplateResponse(request=request, name="project.html", context={"project_id": project_id})

@app.get("/api/projects/{project_id}", response_model=schemas.Project)
def get_project(project_id: int, db: Session = Depends(get_db)):
    db_project = db.query(models.Project).filter(models.Project.id == project_id).first()
    if not db_project:
        raise HTTPException(status_code=404, detail="Not found")
    return db_project

@app.delete("/api/projects/{project_id}")
def delete_project(project_id: int, db: Session = Depends(get_db)):
    db_project = db.query(models.Project).filter(models.Project.id == project_id).first()
    if db_project:
        for phase in db_project.phases:
            if phase.google_event_id: sync_service.delete_event(phase.google_event_id)
            for m in phase.milestones:
                if m.google_event_id: sync_service.delete_event(m.google_event_id)
            for e in phase.events:
                if e.google_event_id: sync_service.delete_event(e.google_event_id)
        db.delete(db_project)
        db.commit()
    return {"ok": True}

@app.put("/api/projects/{project_id}", response_model=schemas.Project)
def update_project(project_id: int, proj_in: schemas.ProjectCreate, db: Session = Depends(get_db)):
    db_project = db.query(models.Project).filter(models.Project.id == project_id).first()
    if not db_project: raise HTTPException(status_code=404)
    db_project.name = proj_in.name
    db_project.description = proj_in.description
    db.commit()
    db.refresh(db_project)
    return db_project

@app.post("/api/phases/{phase_id}/milestones/", response_model=schemas.Milestone)
def create_milestone(phase_id: int, ms: schemas.MilestoneCreate, db: Session = Depends(get_db)):
    event_id = sync_service.sync_event(ms.target_date, ms.target_date, ms.name)
    db_ms = models.Milestone(**ms.model_dump(), phase_id=phase_id, google_event_id=event_id)
    db.add(db_ms)
    db.commit()
    db.refresh(db_ms)
    return db_ms

@app.delete("/api/milestones/{ms_id}")
def delete_milestone(ms_id: int, db: Session = Depends(get_db)):
    ms = db.query(models.Milestone).filter(models.Milestone.id == ms_id).first()
    if ms:
        if ms.google_event_id: sync_service.delete_event(ms.google_event_id)
        db.delete(ms)
        db.commit()
    return {"ok": True}

@app.post("/api/phases/{phase_id}/events/", response_model=schemas.Event)
def create_event(phase_id: int, ev: schemas.EventCreate, db: Session = Depends(get_db)):
    event_id = sync_service.sync_event(ev.start_date, ev.end_date, ev.name)
    db_ev = models.Event(**ev.model_dump(), phase_id=phase_id, google_event_id=event_id)
    db.add(db_ev)
    db.commit()
    db.refresh(db_ev)
    return db_ev

@app.delete("/api/events/{ev_id}")
def delete_event(ev_id: int, db: Session = Depends(get_db)):
    ev = db.query(models.Event).filter(models.Event.id == ev_id).first()
    if ev:
        if ev.google_event_id: sync_service.delete_event(ev.google_event_id)
        db.delete(ev)
        db.commit()
    return {"ok": True}
