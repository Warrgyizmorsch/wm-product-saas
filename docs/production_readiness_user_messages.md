# Production Readiness — your messages, word for word

Kept on request (2026-11-25: "keep a separate file of our chats for messages that I told you").
Your words are copied exactly from the chat — typing mistakes and dictation slips included. Nothing is corrected or shortened here.
My reading of them (the rules) is written separately in `docs/production_execution_rules_draft.md`, section 11, and every rule there names the message it comes from (M-xx).
New messages about this work are added at the bottom, newest last. Times are as recorded in the chat log (UTC).

---

## M-01 — 2026-11-23 07:11 UTC

> track how this is decided, and everything..... all status and everything.... I want to know everything before we change anything.

## M-02 — 2026-11-23 07:33 UTC

> there has to be more issues go and check complete production module in detail.... everything then I will let you know.... what else is needed..... till then you check everything properly.....and log what are the bugs and what does not align with the business rules.... and what is still part of old WC,BOM and routing data....

## M-03 — 2026-11-23 10:53 UTC

> we need to update the rules for Readiness..... Operation Tracking, material requirement being shown double..... and even more

## M-04 — 2026-11-23 10:58 UTC

> Yes, draft rules

## M-05 — 2026-11-23 11:08 UTC

> I'll go through them

## M-06 — 2026-11-23 11:27 UTC

> also along with this, consider, adding op of child BOM and making them a priority when child-BOM materials are not avaliable.... as they need to be created first.... we can place them as per the need of semi-materials.... so it could be like..... op 30 need semi 4 but it was not avaliable so it's raw material was requested, so before starting op 30, ops of semi 4 are to be placed.... I will talk about this in details....

## M-07 — 2026-11-23 11:38 UTC

> okay give me all the questions one by one

## M-08 — 2026-11-23 11:41 UTC

> I didn't understand, show me everything in a proper way.... with visuals and proper flow-chat.

## M-09 — 2026-11-23 11:53 UTC

> I only partly understand what you are saying

## M-10 — 2026-11-23 12:00 UTC

> do one thing apply all chnages.... then I will directly verify them

## M-11 — 2026-11-23 12:30 UTC

> after this go ahead and check schedualing also and then Fix that as per the new current UPdates....

## M-12 — 2026-11-23 12:31 UTC

> after this go ahead and check schedualing also and then Fix that as per the new current UPdates and also fix schedualing to include manual bathes.... and proper screen updates.....

## M-13 — 2026-11-25 04:58 UTC

> I don't think this has been fixed yet, show me the rules you are following to check, then there are few changes that I will tell youuu

## M-14 — 2026-11-25 06:24 UTC

> okay here are the rules..... 
> Okay, so here are small things that we need to punctual ideas. Let's talk it about column by column. Unnecessary is gonna be dependent on many things. Remember each and everything of my decisions as I am seeing it. You can keep a separate file of our chats for messages that I told you. And now I'm here and work it as I say. Make the rules as I say.
> First, let's talk about non-child bombs materials, direct materials. Uh, Now, how this will say ready, waiting, or any other state? It's simple. First, we will check the target. If target is to produce 5 QT... And then we check BOM to determine how many materials are required to produce the 1 QT... We check that against the issued quantity. If that much is issued, we will say partially issued. If that much is not issued, we will say blocked, cannot produce, minimum quantity not issued accordingly. And if all are issued, we will say ready.
> Now for operation work in progress, we will need a better heading for that column, but that will simply check that if we are checking for operation 20, then if it is awaiting operation 10, or is it awaiting operation 20 or any other operation, or is it ready to be performed now as it is independent, or is it in progress or anything. We will check all of these, as these screen will be checked each and every time the operation order is being loaded. It will get updated every time a production order is loaded, that screen is loaded. So all these checks will be performed every time that screen loads. 
> We will check the heading of next column to resources. It won't be just tracking machines, it will be tracking each and every resources that it is required, including machines and manual batches. So we will see if We see if they are ready to produce or not, if any other operation is already scheduled on it. And for machines, any other checks, maintenance and status checks, others that are already in place, we will continue those.
> Subcontractor, it will say uh, subcontracted, because all operations won't be having subcontracting. So for some we will say subcontracted, we will say not subcontracted. And if it will be subcontracted, I will tell you the tests and check later on. For now, just keep this to subcontracted, not subcontracted.
> I will tell you Rest of the rules later. Till now, implement these rules and tell me how they are implemented. Before implementing, tell me what you understand.

## M-15 — 2026-11-25 06:37 UTC

> Yes, you have understood me correctly. Now let me improve a few things over here. Now let's assume operation 20 needs a material that has a child bomb, and that material is in shortage in store. So at the time of production order only, we have decided at the time of creation of draft that if a material is short, we will send the material raw material request for that short material directly. In short, we are directly creating that material in this same PO.... So as discussed earlier, we need to handle that materials creation operation in the same production order. So the operations for creation of that material will be placed inside a drawer inside operation 20, or you can say operation 20.
> You understand the drawer? What I mean by drawer. It's the same arrow drawer that we use in the bomb and other places. You will understand. So in that it will then again show the operations for that those material that needs to be produced and accordingly scene tracking. It's just those operations are not always gonna be shown on the screen. Those are only gonna be shown when the drawer is active by clicking the arrow. Did you understand?

## M-16 — 2026-11-25 06:51 UTC

> I didn't ask you to impliment I only asked you to log and tell me did you understand what I want.....

## M-17 — 2026-11-25 06:55 UTC

> Yes, your idea is correct. And remember, the parent operation will always be in waiting or cannot be executed until the child operations are completed. So again, when we will in the further steps, when we will schedule the operations, we will schedule them accordingly. We do not need to run any of the tests now. Just write all the codes and then give it to me. I will check each and everything. Do not need to run any tests for now. If anything will break, I will tell you. No need for running any test. For this whole conversations, you are not going to run any test on your own unless I explicitly asked you to do it. That's right as enrolled in your memory. And yes, what you have done as changes, those are correct. Go ahead and implement all the changes that I have asked you till now, and then I will test each and everything.

## M-18 — 2026-11-25 07:20 UTC

> Impliment the same drawer system in here, And there will be a few uh, gaps also. Go ahead and document it and tell me before implementing anything. We will check out these gaps and we will implement all the changes together. I have reviewed the above changes. They are looking fine. We will review them in detail later on. You can mark them as partially reviewed. Before that, let's work on this. So now go ahead, do an audit and everything and tell me the gaps. We will discuss on those and then we will move ahead.
> No change has to be implemented yet in this round of research.

## M-19 — 2026-11-25 07:41 UTC

> Just a few changes in this time. The child operations or sub operations will be shown at the top of the parent operations because they are gonna be executed first. As first child materials will be created, sub materials will be created, then the parent material can be created. So sub operations are gonna be shown at the top in the sequence, then the parent operations will be shown.
> So then this one will be set, and again similarly the findings in the scheduler, those things can be set good too.
> Each and every gap should be fixed. Everything should be proper. You know what I want.
> I have given you a main idea of the flow that I want. That's gonna be the flow we are going to go with. Check each and everything that's issue for that. And now this is the implementation phase. Implement all the fixes for those, and then tell me all the fixes that you implemented and where can I check those in the UI.
> Even do these things recursively, everything should be fixed properly. Go ahead. Do not write big tests, and on those tests, those are not very useful. Implement everything according to you, then tell me where can I check it in. I will check it in the UI, and then you can tell me what I what are the bugs, and then I will tell you. Okay, go ahead.

## M-20 — 2026-11-25 08:58 UTC

> <!-- reply 1 -->
> > sub-assembly is needed at two levels
> 
> both op will get the sub link, with the number of quantity required by each op, and user will have a option to produce all sub at initial level if he wants.... did you understand
> 
> 
> okay which commands do I need to run before checking ?

## M-21 — 2026-11-25 09:03 UTC

> I will clear all your questions after I check the UI.... WAIT

## M-22 — 2026-11-25 09:19 UTC

> I reviewed them, they look fine.... The two-level assembly rule is simple. If operation 20 and operation 40 needs semi to be produced, so let's say operation 20 needs 20 and operation 40 needs 20. So both of those operations will get operations as a sequence. Before operation 20, there will be operations to produce semi, And also before operation 40 there will be operation to produce the same semi 20 at each level
> And at each level, there will be a button to set the scheduler. To produce all semis at once. Like before operation 20 itself.. 
> So all this can be done before scheduling. Only after scheduling that button will be disabled, or you can say locked. And similarly, if the same is needed at 3 levels of operation 20, 40, 60, then at operation 20 and operation 40 this button will show. But if a button is clicked at operation 20, then before operation 14, operation 60, there are no longer operations to produce semi. Did you understand? And the quantities will also get combined.
> 
> Did you understand.... ?

## M-23 — 2026-11-25 09:31 UTC

> Good, along with the change a few more things. And remember, this operation rule will be for each child bomb. So let's say if operation 20 needs two separate semis to be produced, semi 10 and semi 20. So for both there will be operations to be produced.
> Yeah. For the sequence for both semis operations, the semi that is mentioned first in the bomb will be produced first. Simple, not much to think about it over here.
> If any changes, currently the requests are being sent as soon as the drafts are getting created. We will make that process manual and that process will no longer be automated. The user will select which items request are to be sent per PO and multiple requests can be sent per PO.
> The UI for the material is set. I will tell you that part later, but for now, this is the flow that we will be going for. So, this is the production order is drafted. It's gonna be kept in draft. All these things will be checked proper in detail, and then against that we have edit options. So then the drafted PO can be edited. We will improve the edition screen also, and then once its requests are sent, its status will be locked. So then the PO cannot be edited, neither it can be deleted. It can only be cancelled then. And then after we receive the material, we will release it for scheduling, and its schedule will be generated, and then accordingly we will go further with the flow. But this is the flow that we are going to follow for now.

## M-24 — 2026-11-25 09:47 UTC

> <!-- reply 3 -->
> > "Produced first" also holds in the scheduler. The second semi starts only after the first semi's last operation finishes.
> 
> Yes, but just do not make it a hard log. It would be just an indication for scheduling.
> 
> <!-- reply -->
> > Tell me if you want a real new status instead
> 
> yes a new status called locked.
> 
> <!-- reply -->
> > Release keeps today's rule. If a request exists, the store must have issued material first, at least partly. If the order has planned materials, at least one request must have been sent before release. An order with no planned materials can be released at once
> 
> yes but We will turn it from a hard lock into a soft lock with a warning.
> 
> 
> The warning will simply say that materials, do you still want to schedule it? The materials are not released, do you still want to schedule it? If the user says okay, then he can schedule it. Even with 0 allocation.
> 
> For C4, we will check a few things, then we will talk about on C4. Till that, implement each and everything that we have talked about till now, and also the updates that I have sent you. So with all those, go ahead and start the implementation, implement everything.

## M-25 — 2026-11-25 10:26 UTC

> why is target soo high, and no option to produce all at once, when test-semi-02 is also needed by parent op1, check everything and then tell me

## M-26 — 2026-11-25 10:39 UTC

> okay, Buttons are at correct place... do not change that, do fix the quantity issue but first explain this to me in detail... the calculation....

## M-27 — 2026-11-25 10:53 UTC

> Yes, apply A

## M-28 — 2026-11-25 11:21 UTC

> check if this op really need this much raw materials or are they some mixed values... check how they are calculated

## M-29 — 2026-11-25 11:22 UTC

> okay.

## M-30 — 2026-11-25 11:42 UTC

> Show this Form In a pop-up modal and that also not on this screen, on the screen in photo 2, add a btn "Req materials" or "Generate Requisition slip " there that will open the modal.... 
> 
> GO and Impliment this

## M-31 — 2026-11-25 11:45 UTC

> it is showing wrong status

## M-32 — 2026-11-25 11:47 UTC

> also track how does it trestes the items that are less is store but are purchased not produced...

## M-33 — 2026-11-25 11:50 UTC

> okay no issues, everything is fine for now, let this be it.

## M-34 — 2026-11-25 12:27 UTC

> now let this edit form be able to edit everything, It will have all the features as ADD form

## M-35 — 2026-11-25 12:46 UTC

> Just make sure a final few changes. If a material acquisition is made and is also if it's also being reserved, then also the production order cannot be edited.

## M-36 — 2026-11-25 12:51 UTC

> delete and edit will be same, only thing is delete will cancel then soft delete the MR

## M-37 — 2026-11-25 12:57 UTC

> also, edit will delete the old MR also when the Product, quantity, BOM, routing or Tab 1 choices changed

## M-38 — 2026-11-25 12:59 UTC

> and what about the PR raised against that old MR

## M-39 — 2026-11-25 13:01 UTC

> A

