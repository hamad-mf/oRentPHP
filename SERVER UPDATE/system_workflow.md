---
description: Comprehensive System operational Workflow & Walkthrough for Benz Rent
---

# 🏢 Benz Rent ERP - Full System Operational Workflow

This document provides a comprehensive, end-to-end checklist walkthrough of the entire Benz Rent application. It covers all major modules—CRM, Reservations, Vehicles, Accounting, and more. Use these checklists to ensure standardized, error-free daily operations, especially if handing off tasks to an assistant.

---

## 1. 📊 Dashboard & Overview
The command center for daily operations. 

**Checklist for Daily Overview:**
- [ ] Log in to the ERP.
- [ ] Review the **Dashboard** for high-level metrics (active reservations, available vehicles, recent revenue).
- [ ] **Action Required**: Scan the Kanban Board and Follow-ups section. Identify any items with a *distinct red background and border*. These are **Overdue Follow-ups** and must be handled immediately before any new tasks.
- [ ] Verify there are no pending urgent alerts or system warnings.

---

## 2. 👥 CRM & Lead Management 
The lifecycle of acquiring and managing new prospective clients.

**Checklist for Lead Operations:**
- [ ] **Capture Lead**: Navigate to **CRM > Leads** and click **Add New Lead**.
- [ ] **Fill Details**: Enter Name, Email, Phone, Inquiry type (e.g., Daily/Monthly rental), and Source.
- [ ] **Assignment**: Assign a Staff member and set the initial Status.
- [ ] **Pipeline Movement**: Navigate to **CRM > Pipeline**. Identify leads that require updating and drag-and-drop their cards to the next logical stage (e.g., *Contacted* to *Interested*).
- [ ] **Follow-ups**: Ensure every active lead has a future-dated Follow-up scheduled (Call, Meeting, Email) with clear Notes describing the objective.
- [ ] **Conversion**: When final, change the lead status to **Closed Won** (to generate a reservation) or **Closed Lost** (mandatory: document the reason).

---

## 3. 🚗 Vehicle Fleet & Locations Management
Managing your core assets and physical premises.

**Checklist for Vehicle Management:**
- [ ] **Add/Edit Vehicle**: Navigate to **Vehicles**.
- [ ] **Required Pricing**: Ensure both **Daily Rates** and **Monthly Rates** are accurately populated for the specific vehicle class, make, and model.
- [ ] **Inspections**: Run a `Vehicle Inspection` before dispatch and upon return.
- [ ] **Maintenance Flag**: If an inspection fails, flag the vehicle for maintenance. Verify it is removed from the active available pool.
- [ ] **Location Audit**: Open the **Locations** module. Verify the showroom-style interface is accurately reflecting the physical volume of the fleet at each branch.

---

## 4. 📅 Reservations & Flexible Rentals
The core revenue engine for Benz Rent.

**Checklist for Managing Reservations:**
- [ ] **Create Booking**: Start the "New Reservation" flow from a converted Lead or Client profile.
- [ ] **Dynamic Pricing Check**: Select the **Rental Type**. 
    - [ ] *If Daily*: Verify the subtotal calculates using the vehicle's standard daily rate * days.
    - [ ] *If Monthly*: Verify the system automatically applies the vehicle's discounted `monthly_rate`.
- [ ] **Dispatch/Handover**: Complete the pre-rental Vehicle Inspection, upload assigned KYC documents via the `DocumentController`, and issue the vehicle.
- [ ] **Returns & Overdue Processing**: 
    - [ ] When the vehicle is physically returned, log the precise `actual_end_date`.
    - [ ] Check the final bill: If the return was late, verify the system automatically calculated and applied the `overdue_amount` based on the elapsed time past the agreed deadline.

---

## 5. 💰 Financial Tracking (Expenses & Investments)
Managing the cash flow and corporate investments.

**Checklist for Financial Logs:**
- [ ] **Log Expenses**: Navigate to **Expenses**. Categorize operational costs (e.g., fuel, repairs) and link them to the specific vehicle ID.
- [ ] **Investments**: Log any capital injections or fleet financing in the **Investments** module to track ROI.
- [ ] **Challans/Fines**: If a traffic ticket is received, log it in the **Challans** module. Ensure it is cross-referenced with the active Reservation during that date to bill the appropriate Client.

---

## 6. 📂 System Administration, Tracking, & Compliance

**Checklist for System Tracking:**
- [ ] **Staff Permissions**: Verify Staff roles (Admins control College Modules, edit permissions, and lead assignments).
- [ ] **Manual GPS Tracking Log**: 
    - [ ] Confirm if the vehicle is active daily in its designated tracking area (Log: Yes/No).
    - [ ] Identify if the vehicle has a new operational assignment. If yes, **Update the Area** designation in the system.
- [ ] **Documents Audit**: Check the centralized Documents Management hub to ensure system-wide files and client agreements are securely stored and accessible.

---

## 🚀 Daily Routine Complete Checklist for Staff/Assistants
- [ ] **1. Morning Briefing**: Check the Dashboard. Resolve all red (overdue) follow-ups and pending Challans.
- [ ] **2. GPS active Check**: Verify active tracking Yes/No for the active fleet and update designated areas if needed.
- [ ] **3. CRM Sweep**: Move cold Leads backward or to lost. Prioritize "Negotiation" stage cards on the horizontal Pipeline. 
- [ ] **4. Fleet Audit**: Review vehicles flagged as "Maintenance". Check for vehicles overdue for return and follow up with the client.
- [ ] **5. End of Day Compliance**: Guarantee all interactions, inspection logs, actual end dates for returns, and manual GPS tracking updates have been accurately recorded in the ERP.
