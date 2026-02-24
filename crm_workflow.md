---
description: Comprehensive CRM, Lead Management, and Pipeline Workflow
---

# 🚀 Full CRM & Lead Management Workflow

This workflow guides you (or an AI assistant) through the complete lifecycle of CRM operations. It is designed with strict checklists to ensure no step is missed during execution.

## 1. 📥 Lead Capture & Creation (Lead Management)
All new opportunities start as a Lead. Follow this checklist to capture a lead accurately.

**Checklist for Lead Creation:**
- [ ] Navigate to the **CRM > Leads** management list.
- [ ] Click the **Add New Lead** button.
- [ ] Enter **Contact Information**: Name, Email, Phone number.
- [ ] Enter **Lead Details**: Specific inquiry information (e.g., vehicle or college inquiry).
- [ ] Select **Source**: Identify where the lead originated (e.g., Organic, Ad Campaign, Referral).
- [ ] **Assign To**: Select a specific staff member to handle this lead.
- [ ] Set **Status**: Initialize the lead status to 'New' or 'Uncontacted'.
- [ ] **Submit**: Save the form to store the lead in the system via the `LeadsController`.
- [ ] **Verify**: Confirm the lead appears correctly in the Leads list or Pipeline.

## 2. 🗂️ The CRM Pipeline (Kanban Board)
The pipeline is a visual, horizontal scrolling Kanban board holding active leads.

**Checklist for Pipeline Management:**
- [ ] Navigate to **CRM > Pipeline**.
- [ ] Locate the target lead card on the board.
- [ ] Review current stage (*New*, *Contacted*, *Interested*, *Negotiation*, *Closed Won*, *Closed Lost*).
- [ ] **Action**: Drag and drop the lead card to the appropriate next stage based on recent interactions.
- [ ] **Verify Update**: Ensure the system registers the status change (check for visual confirmation or database update).
- [ ] **Quick Actions**: Utilize the quick "Edit" or "Delete" buttons directly on the card if immediate modifications are required.

## 3. 📞 Managing Follow-ups
Consistent follow-ups prevent leads from going cold.

**Checklist for Scheduling Follow-ups:**
- [ ] Open the detailed view for the specific Lead.
- [ ] Navigate to the **Follow-ups** tab/section.
- [ ] Define the **Type** of follow-up (Call, Meeting, Email).
- [ ] Set a required **Date & Time** in the future.
- [ ] Add explicit **Notes** describing the objective of this follow-up.
- [ ] **Save** the follow-up task.
- [ ] **Action Overdue items**: Check the Dashboard. If any follow-up has a red background/border, it is *overdue*. Execute this follow-up immediately before handling new ones.

## 4. 📝 Activity Logging and Notes
Historical context is vital for CRM continuity.

**Checklist for Logging Activity:**
- [ ] Open the detailed view for the specific Lead.
- [ ] Locate the **Notes** or **Activity** section.
- [ ] Document the details of the most recent interaction (e.g., "Left voicemail," "Client requested pricing for monthly SUV rental").
- [ ] **Save** the note.
- [ ] **Review**: Briefly scan the Activity Timeline to ensure the narrative of the lead makes sense for any staff member reviewing it.

## 5. ✅ Finalizing and Conversion
Moving a lead to a terminal state.

**Checklist for Closing a Lead:**
- [ ] Verify all necessary interactions and notes are logged.
- [ ] Move the Lead to a final stage via the Pipeline or Edit screen.
- [ ] **If Closed Won**:
  - [ ] Update status to **Closed Won**.
  - [ ] Initiate the downstream process (e.g., generate a formal `Reservation`, route to `DocumentController` for contracts).
- [ ] **If Closed Lost**:
  - [ ] Update status to **Closed Lost**.
  - [ ] **Mandatory**: Enter the 'Lost Reason' (e.g., Price too high, went with competitor) so the business can track metrics.

## 6. 🛠️ Daily CRM Best Practices (Assistant Automation)
If executing a daily CRM sweep, follow these steps in order:
1. **Sweep Dashboard**: Identify and prioritize formatting/resolving any *Overdue Follow-ups* (red indicators).
2. **Review Pipeline**: Identify leads lingering in a single stage for too long and schedule follow-ups or move them to Closed Lost.
3. **Data Integrity Check**: Ensure all newly entered leads have an assigned staff member and a valid contact method.
